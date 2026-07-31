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
use StoreSeeder\Platforms\Status as CanonicalStatus;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical transaction into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Transaction extends Writer {
	/**
	 * Canonical transaction status to Fluent Cart's spelling.
	 *
	 * Its own `Status` helper has succeeded, authorized, pending, failed, refunded and dispute_lost.
	 * Two of the six differ from the canonical names, which is exactly why the mapping lives here
	 * and not in the generator.
	 *
	 * @since 1.1.0
	 *
	 * @var array<string, string>
	 */
	const TRANSACTION_STATUS = array(
		CanonicalStatus::PENDING    => 'pending',
		CanonicalStatus::AUTHORIZED => 'authorized',
		CanonicalStatus::COMPLETED  => 'succeeded',
		CanonicalStatus::FAILED     => 'failed',
		CanonicalStatus::REFUNDED   => 'refunded',
		CanonicalStatus::DISPUTED   => 'dispute_lost',
	);

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
		$order = $this->parent_order();

		if ( ! $order ) {
			return new WP_Error(
				'no_orders',
				__( 'No orders were found matching the request. Generate orders before generating transactions.', 'storeseeder' )
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
			// Mapped, not passed through: `completed` is `succeeded` here and `disputed` is
			// `dispute_lost`, and an unmapped status is a string Fluent Cart's own status filters
			// never match — so the transaction exists and appears nowhere.
			'status'              => self::TRANSACTION_STATUS[ $entity['status'] ] ?? 'succeeded',
			// fct_order_transactions.total is a BIGINT of integer cents, read
			// back through Helper::toDecimal(), so the canonical minor units go
			// straight in.
			'total'               => (int) $entity['total'],
			'rate'                => $entity['rate'],
			// When the money moved. Eloquent stamps `created_at` itself, which put every
			// transaction on a two-year-old order at today's date.
			'created_at'          => $this->transaction_date( $order, $entity ),
			'meta'                => $this->transaction_meta( $entity ),
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

		return $this->filter_result( $result, $transaction->id, $transaction_data );
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
			$created = OrderTransactionModel::query()->create( $data );

			// Eloquent's create() is reached through __callStatic, so its declared type is
			// Builder|Model rather than this model. Narrowing here is what makes the
			// nullable return type above true rather than merely intended.
			return $created instanceof OrderTransactionModel ? $created : null;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Remove a generated transaction.
	 *
	 * The order it belongs to stays: a transaction is one payment attempt against it, and
	 * the order is a separate resource with its own ledger entry.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The identifier reported when the row was created.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		return $this->delete_model( OrderTransactionModel::class, $id );
	}

	/**
	 * The order this transaction belongs to.
	 *
	 * A requested customer or a status filter narrows the draw. Both were declared on the admin and
	 * the MCP ability and read by nothing, so a run asked for one customer's payments got the whole
	 * store's.
	 *
	 * @since 1.1.0
	 *
	 * @return OrderModel|null
	 */
	private function parent_order(): ?OrderModel {
		$query    = OrderModel::query();
		$customer = (int) ( $this->params['customer_id'] ?? 0 );

		if ( $customer > 0 ) {
			$query->where( 'customer_id', $customer );
		}

		$statuses = array_values(
			array_filter( (array) ( $this->params['order_status_filter'] ?? array() ), 'is_string' )
		);

		if ( array() !== $statuses ) {
			// Mapped through the same table the order writer uses, so the filter speaks the
			// canonical vocabulary the rest of the plugin does rather than Fluent Cart's.
			$mapped = array();

			foreach ( $statuses as $status ) {
				$mapped[] = Order::ORDER_STATUS[ $status ] ?? $status;
			}

			$query->whereIn( 'status', $mapped );
		}

		$order = $query->inRandomOrder()->first();

		return $order instanceof OrderModel ? $order : null;
	}

	/**
	 * When this transaction happened.
	 *
	 * Never before the order it belongs to. The generated date is drawn from the last ninety days
	 * with no knowledge of the parent, and a payment that predates its own order is the kind of
	 * detail that makes a settlement report impossible to reconcile.
	 *
	 * @since 1.1.0
	 *
	 * @param OrderModel           $order  The parent order.
	 * @param array<string, mixed> $entity Canonical transaction entity.
	 *
	 * @return string
	 */
	private function transaction_date( OrderModel $order, array $entity ): string {
		$generated = (string) ( $entity['created_at'] ?? '' );

		if ( '' === $generated ) {
			return current_time( 'Y-m-d H:i:s' );
		}

		$placed = (string) ( $order->created_at ?? '' );

		if ( '' !== $placed && strtotime( $generated ) < strtotime( $placed ) ) {
			return $placed;
		}

		return $generated;
	}

	/**
	 * The gateway metadata for this transaction.
	 *
	 * `include_gateway_metadata` off means an empty column rather than a payer with no email in it:
	 * a shape that exists and holds nothing reads as data loss to anybody looking at the row.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical transaction entity.
	 *
	 * @return array<string, mixed>
	 */
	private function transaction_meta( array $entity ): array {
		if ( isset( $entity['with_metadata'] ) && ! $entity['with_metadata'] ) {
			return array();
		}

		return array(
			'payer' => array(
				'email_address' => (string) ( $entity['payer_email'] ?? '' ),
			),
		);
	}
}
