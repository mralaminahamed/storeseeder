<?php
/**
 * Transaction Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\OrderTransaction as OrderTransactionModel;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Transaction Generator Class
 *
 * Generates realistic fake transaction data for Fluent Cart testing and development.
 */
class Transaction extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'transaction';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'transactions' => 'Fluent Cart Transactions',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates payment transactions with various methods and statuses for testing Fluent Cart payment processing.';
	}

	/**
	 * Generate a single transaction
	 *
	 * @return WP_Error|array Single transaction data, error, or false on failure.
	 */
	protected function generate_single_item() {
		// Check if Fluent Cart OrderTransaction model is available.
		if ( ! class_exists( OrderTransactionModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart OrderTransaction model not found. Please ensure Fluent Cart plugin is active.', 'fluent-cart-fakerpress' ) );
		}

		// A transaction is a child of an order; without one there is nothing to
		// attach it to, and order_id/order_type would have to be invented.
		$order = $this->random_order();

		if ( null === $order ) {
			return new WP_Error(
				'no_orders',
				__( 'No orders were found. Generate orders before generating transactions.', 'fluent-cart-fakerpress' )
			);
		}

		$transaction_data = $this->generate_transaction_data( $order );
		$transaction      = $this->create_transaction( $transaction_data );

		if ( ! $transaction ) {
			return new WP_Error( 'transaction_creation_failed', __( 'Failed to create transaction.', 'fluent-cart-fakerpress' ) );
		}

		$result = array(
			'id'             => $transaction->id,
			'order_id'       => $transaction->order_id,
			'total'          => $transaction->total,
			'payment_method' => $transaction->payment_method,
			'status'         => $transaction->status,
			'currency'       => $transaction->currency,
			'created_at'     => $transaction->created_at,
		);

		/**
		 * Filters the transaction generation result data.
		 *
		 * Allows developers to modify the returned transaction data after generation.
		 *
		 * @since 1.0.0
		 * @hook  fluent_cart_fakerpress_transaction_generation_result
		 *
		 * @param array $result            The transaction generation result data.
		 * @param int   $transaction_id    The created transaction ID.
		 * @param array $transaction_data  The original transaction data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_transaction_generation_result', $result, $transaction->id, $transaction_data );
	}

	/**
	 * Draw a real order to attach the transaction to.
	 *
	 * The order_id column is a foreign key into fct_orders, and order_type has
	 * to match the parent order. Inventing either produces transactions that
	 * belong to no order and are dropped from every report joining the two.
	 *
	 * @since 2.1.0
	 *
	 * @return object|null Order row with id and type, or null when the store
	 *                     has no orders yet.
	 */
	private function random_order(): ?object {
		$order = OrderModel::query()->inRandomOrder()->first();

		return $order ? $order : null;
	}

	/**
	 * Generate transaction data
	 *
	 * @param object $order Parent order row, carrying id and type.
	 *
	 * @return array Transaction data
	 */
	private function generate_transaction_data( object $order ): array {
		$methods  = array( 'stripe', 'paypal', 'bank_transfer', 'cod' );
		$statuses = array( 'succeeded', 'pending', 'failed', 'refunded' );
		// Status::getTransactionTypes() allows charge, refund, dispute and
		// signup_fee. 'payment' and 'chargeback' are not Fluent Cart values —
		// transactions carrying them are invisible to the refund UI and to the
		// Refund generator, which filters on transaction_type = 'charge'.
		$types = array( 'charge', 'refund', 'dispute' );

		$payment_method = $this->get_faker()->randomElement( $methods );
		$status         = $this->get_faker()->randomElement( $statuses );

		$data = array(
			'order_id'            => (int) $order->id,
			// Copied from the parent order, the way Fluent Cart does it. The
			// allowed set is payment / subscription / renewal; 'order' is not
			// one, and StatusHelper filters revenue reporting on 'payment', so
			// generated transactions were excluded from every report.
			'order_type'          => $order->type,
			'vendor_charge_id'    => strtoupper( $this->get_faker()->bothify( 'CH-##########' ) ),
			'payment_method'      => $payment_method,
			'payment_mode'        => 'live',
			'payment_method_type' => $payment_method,
			'currency'            => 'USD',
			'transaction_type'    => $this->get_faker()->randomElement( $types ),
			'status'              => $status,
			// fct_order_transactions.total is a BIGINT of integer cents, read
			// back through Helper::toDecimal(). Dollars here render 100x small.
			'total'               => (int) round( $this->get_faker()->randomFloat( 2, 10, 1000 ) * 100 ),
			'rate'                => 1.0,
			'meta'                => array(
				'payer' => array(
					'email_address' => $this->get_faker()->email(),
				),
			),
		);

		// Add card details for card payments.
		if ( in_array( $payment_method, array( 'stripe', 'paypal' ), true ) && 'succeeded' === $status ) {
			$data['card_last_4'] = $this->get_faker()->numberBetween( 1000, 9999 );
			$data['card_brand']  = $this->get_faker()->randomElement( array( 'visa', 'mastercard', 'amex', 'discover' ) );
		}

		return $data;
	}

	/**
	 * Create transaction in Fluent Cart
	 *
	 * @param array $data Transaction data.
	 *
	 * @return OrderTransactionModel|null Created transaction instance
	 */
	private function create_transaction( array $data ): ?OrderTransactionModel {
		try {
			$transaction = OrderTransactionModel::query()->create( $data );
			return $transaction;
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
