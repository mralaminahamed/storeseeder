<?php
/**
 * Refund Generator.
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Generators
 */

namespace FluentCartFakerPress\Generators;

defined( 'ABSPATH' ) || exit;

use FluentCart\App\Helpers\Status;
use FluentCart\App\Models\OrderTransaction;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

/**
 * Generates refund transactions against existing successful Fluent Cart charge transactions.
 *
 * @since 1.0.0
 */
class Refund extends Generator {

	/**
	 * Refund reasons.
	 *
	 * @var string[]
	 */
	private const REASONS = array(
		'Item not as described',
		'Duplicate order placed',
		'Item arrived damaged',
		'Wrong item received',
		'Item never arrived',
		'Changed mind after purchase',
		'Quality not as expected',
		'Billing error',
	);

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type(): string {
		return 'refund';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_supported_types(): array {
		return array(
			'full'    => __( 'Full refund', 'fluent-cart-fakerpress' ),
			'partial' => __( 'Partial refund', 'fluent-cart-fakerpress' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Generates full and partial refund transactions against existing successful Fluent Cart charge transactions.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function generate_single_item() {
		if ( ! defined( 'FLUENT_CART_VERSION' ) || ! class_exists( OrderTransaction::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart transaction model not found. Ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		// Base the refund on an existing successful charge so the integer-cents scale matches the schema.
		$charge = OrderTransaction::query()
			->where( 'transaction_type', Status::TRANSACTION_TYPE_CHARGE )
			->where( 'status', Status::TRANSACTION_SUCCEEDED )
			->inRandomOrder()
			->first();

		if ( ! $charge ) {
			return new WP_Error( 'no_eligible_transaction', __( 'No successful charge transactions found for refund generation. Generate orders/transactions first.', 'fluent-cart-fakerpress' ) );
		}

		$charge_total = (int) $charge->total; // integer cents

		if ( $this->get_faker()->boolean( 50 ) || $charge_total <= 0 ) {
			$type   = 'full';
			$amount = $charge_total;
		} else {
			$type   = 'partial';
			$amount = (int) round( $charge_total * $this->get_faker()->randomFloat( 2, 0.1, 0.9 ) );
		}

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_refund_amount', __( 'Computed refund amount is zero; source transaction has no value.', 'fluent-cart-fakerpress' ) );
		}

		$reason     = $this->get_faker()->randomElement( self::REASONS );
		$order_type = ( $charge->order && isset( $charge->order->type ) ) ? $charge->order->type : 'product';

		$refund = OrderTransaction::query()->create(
			array(
				'order_id'            => $charge->order_id,
				'order_type'          => $order_type,
				'payment_method'      => $charge->payment_method,
				'payment_mode'        => $charge->payment_mode,
				'payment_method_type' => $charge->payment_method_type,
				'transaction_type'    => Status::TRANSACTION_TYPE_REFUND,
				'subscription_id'     => $charge->subscription_id,
				'status'              => Status::TRANSACTION_REFUNDED,
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
			return new WP_Error( 'refund_creation_failed', __( 'Failed to create refund transaction.', 'fluent-cart-fakerpress' ) );
		}

		return array(
			'id'        => (int) $refund->id,
			'order_id'  => (int) $charge->order_id,
			'parent_id' => (int) $charge->id,
			'amount'    => $amount,
			'currency'  => $charge->currency,
			'status'    => Status::TRANSACTION_REFUNDED,
			'type'      => $type,
			'reason'    => $reason,
		);
	}
}
