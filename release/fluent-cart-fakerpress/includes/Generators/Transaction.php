<?php
/**
 * Transaction Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

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

		$transaction_data = $this->generate_transaction_data();
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
	 * Generate transaction data
	 *
	 * @return array Transaction data
	 */
	private function generate_transaction_data(): array {
		$methods  = array( 'stripe', 'paypal', 'bank_transfer', 'cod' );
		$statuses = array( 'succeeded', 'pending', 'failed', 'refunded' );
		$types    = array( 'payment', 'refund', 'chargeback' );

		$payment_method = $this->get_faker()->randomElement( $methods );
		$status         = $this->get_faker()->randomElement( $statuses );

		$data = array(
			'order_id'            => $this->get_faker()->numberBetween( 1, 1000 ),
			'order_type'          => 'order',
			'vendor_charge_id'    => strtoupper( $this->get_faker()->bothify( 'CH-##########' ) ),
			'payment_method'      => $payment_method,
			'payment_mode'        => 'live',
			'payment_method_type' => $payment_method,
			'currency'            => 'USD',
			'transaction_type'    => $this->get_faker()->randomElement( $types ),
			'status'              => $status,
			'total'               => $this->get_faker()->randomFloat( 2, 10, 1000 ),
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
