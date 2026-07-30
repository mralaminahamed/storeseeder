<?php
/**
 * Fluent Cart refund writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Fluent_Cart\Writers;

use FluentCart\App\Helpers\Status as FluentStatus;
use FluentCart\App\Models\OrderTransaction;
use StoreSeeder\Abstracts\Writer;
use StoreSeeder\Platform\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical refund into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Refund extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::REFUND;
	}

	/**
	 * Create a Fluent Cart refund transaction.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical refund entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( OrderTransaction::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart transaction model not found. Ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		// Base the refund on an existing successful charge so the integer-cents scale matches the schema.
		$charge = OrderTransaction::query()
			->where( 'transaction_type', FluentStatus::TRANSACTION_TYPE_CHARGE )
			->where( 'status', FluentStatus::TRANSACTION_SUCCEEDED )
			->inRandomOrder()
			->first();

		if ( ! $charge ) {
			return new WP_Error( 'no_eligible_transaction', __( 'No successful charge transactions found for refund generation. Generate orders/transactions first.', 'storeseeder' ) );
		}

		$charge_total = (int) $charge->total; // Integer cents.
		$fraction     = $entity['fraction'] ?? null;

		// A charge worth nothing can only be refunded in full, whatever the entity
		// asked for — and that refund is then rejected below, as it always has been.
		if ( 'full' === $entity['kind'] || null === $fraction || $charge_total <= 0 ) {
			$type   = 'full';
			$amount = $charge_total;
		} else {
			$type   = 'partial';
			$amount = (int) round( $charge_total * (float) $fraction );
		}

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_refund_amount', __( 'Computed refund amount is zero; source transaction has no value.', 'storeseeder' ) );
		}

		$reason     = $entity['reason'];
		$order_type = ( $charge->order && isset( $charge->order->type ) ) ? $charge->order->type : 'product';

		$refund = OrderTransaction::query()->create(
			array(
				'order_id'            => $charge->order_id,
				'order_type'          => $order_type,
				'payment_method'      => $charge->payment_method,
				'payment_mode'        => $charge->payment_mode,
				'payment_method_type' => $charge->payment_method_type,
				'transaction_type'    => FluentStatus::TRANSACTION_TYPE_REFUND,
				'subscription_id'     => $charge->subscription_id,
				'status'              => FluentStatus::TRANSACTION_REFUNDED,
				'currency'            => $charge->currency,
				'total'               => $amount,
				'meta'                => array(
					'parent_id' => $charge->id,
					'reason'    => $reason,
				),
				'uuid'                => md5( (string) wp_generate_uuid4() ),
			)
		);

		if ( ! $refund || ! $refund->id ) {
			return new WP_Error( 'refund_creation_failed', __( 'Failed to create refund transaction.', 'storeseeder' ) );
		}

		return array(
			'id'        => (int) $refund->id,
			'order_id'  => (int) $charge->order_id,
			'parent_id' => (int) $charge->id,
			'amount'    => $amount,
			'currency'  => $charge->currency,
			'status'    => FluentStatus::TRANSACTION_REFUNDED,
			'type'      => $type,
			'reason'    => $reason,
		);
	}
}
