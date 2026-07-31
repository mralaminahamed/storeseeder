<?php
/**
 * Fluent Cart transaction writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use Exception;
use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\OrderTransaction as OrderTransactionModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical transaction into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Transaction extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::TRANSACTION;
	}

	/**
	 * Create a Fluent Cart order transaction.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical transaction entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		// Check if Fluent Cart OrderTransaction model is available.
		if ( ! class_exists( OrderTransactionModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart OrderTransaction model not found. Please ensure Fluent Cart plugin is active.', 'storeseeder' ) );
		}

		// A transaction is a child of an order; without one there is nothing to
		// attach it to, and order_id/order_type would have to be invented. The
		// order_id column is a foreign key into fct_orders, and order_type has to
		// match the parent order — inventing either produces transactions that belong
		// to no order and are dropped from every report joining the two.
		$order = OrderModel::query()->inRandomOrder()->first();

		if ( ! $order ) {
			return new WP_Error(
				'no_orders',
				__( 'No orders were found. Generate orders before generating transactions.', 'storeseeder' )
			);
		}

		$transaction_data = array(
			'order_id'            => (int) $order->id,
			// Copied from the parent order, the way Fluent Cart does it. The
			// allowed set is payment / subscription / renewal; 'order' is not
			// one, and StatusHelper filters revenue reporting on 'payment', so
			// generated transactions were excluded from every report.
			'order_type'          => $order->type,
			'vendor_charge_id'    => $entity['vendor_charge_id'],
			'payment_method'      => $entity['payment_method'],
			'payment_mode'        => $entity['payment_mode'],
			'payment_method_type' => $entity['payment_method_type'],
			'currency'            => $entity['currency'],
			'transaction_type'    => $entity['transaction_type'],
			'status'              => $entity['status'],
			// fct_order_transactions.total is a BIGINT of integer cents, read
			// back through Helper::toDecimal(), so the canonical minor units go
			// straight in.
			'total'               => (int) $entity['total'],
			'rate'                => $entity['rate'],
			'meta'                => array(
				'payer' => array(
					'email_address' => $entity['payer_email'],
				),
			),
		);

		if ( isset( $entity['card_last_4'] ) ) {
			$transaction_data['card_last_4'] = $entity['card_last_4'];
			$transaction_data['card_brand']  = $entity['card_brand'];
		}

		$transaction = $this->create_transaction( $transaction_data );

		if ( ! $transaction ) {
			return new WP_Error( 'transaction_creation_failed', __( 'Failed to create transaction.', 'storeseeder' ) );
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
		 * @hook  storeseeder_transaction_generation_result
		 *
		 * @param array $result            The transaction generation result data.
		 * @param int   $transaction_id    The created transaction ID.
		 * @param array $transaction_data  The original transaction data used for creation.
		 */
		return apply_filters( 'storeseeder_transaction_generation_result', $result, $transaction->id, $transaction_data );
	}

	/**
	 * Create transaction in Fluent Cart.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped transaction data.
	 *
	 * @return OrderTransactionModel|null Created transaction instance.
	 */
	private function create_transaction( array $data ): ?OrderTransactionModel {
		try {
			return OrderTransactionModel::query()->create( $data );
		} catch ( Exception $e ) {
			return null;
		}
	}
}
