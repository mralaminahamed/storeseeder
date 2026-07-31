<?php
/**
 * Transaction Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;
use StoreSeeder\Platforms\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Transaction Generator Class
 *
 * Shapes payment transactions. A transaction is a child of an order, so the writer
 * finds the parent — order_id and order_type cannot be invented without producing
 * transactions that belong to no order and drop out of every report joining the two.
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
			'transactions' => __( 'Transactions', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates payment transactions with various methods and statuses for testing payment processing.';
	}

	/**
	 * Build a canonical transaction
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		$payment_method = $this->payment_method();
		$type           = $this->transaction_type();
		$status         = $this->status( $type );
		$metadata       = $this->metadata_enabled();

		$entity = array(
			'vendor_charge_id'    => strtoupper( $this->get_faker()->bothify( 'CH-##########' ) ),
			'payment_method'      => $payment_method,
			'payment_mode'        => 'live',
			'payment_method_type' => $payment_method,
			'currency'            => 'USD',
			'transaction_type'    => $type,
			'status'              => $status,
			// Integer minor units. Major units here render a hundred times small on
			// any platform that stores cents.
			'total'               => $this->total(),
			'rate'                => 1.0,
			'payer_email'         => $metadata ? $this->get_faker()->email() : '',
			// When the money moved. Left to the platform, every transaction on a two-year-old
			// order was stamped today, which makes a settlement report meaningless.
			'created_at'          => gmdate(
				'Y-m-d H:i:s',
				time() - $this->get_faker()->numberBetween( 0, 90 ) * DAY_IN_SECONDS
			),
			// Whether gateway detail was asked for at all, so a writer keeping it in a JSON column
			// knows to leave that column empty rather than storing an empty shape.
			'with_metadata'       => $metadata,
		);

		// Card details only exist for a card payment that actually went through, and only when
		// gateway metadata was asked for.
		if ( $metadata && in_array( $payment_method, array( 'stripe', 'paypal', 'credit_card', 'debit_card' ), true ) && Status::COMPLETED === $status ) {
			$entity['card_last_4'] = $this->get_faker()->numberBetween( 1000, 9999 );
			$entity['card_brand']  = $this->get_faker()->randomElement( array( 'visa', 'mastercard', 'amex', 'discover' ) );
		}

		return $entity;
	}

	/**
	 * One of the requested payment methods.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function payment_method(): string {
		// `payment_gateways` is what the admin and the MCP ability called this; the endpoint called
		// it `payment_methods`. Both work, since both shipped.
		$requested = $this->generation_params['payment_methods'] ?? ( $this->generation_params['payment_gateways'] ?? array() );
		$methods   = array_values( array_filter( (array) $requested, 'is_string' ) );

		if ( array() === $methods ) {
			$methods = array( 'stripe', 'paypal', 'bank_transfer', 'cod' );
		}

		return (string) $this->get_faker()->randomElement( $methods );
	}

	/**
	 * Charge, refund or dispute.
	 *
	 * `include_refunds` and `refund_percentage` were declared and ignored, so a third of every run
	 * came out as refunds however few were asked for, and switching them off did nothing at all.
	 * Disputes stay rare: a store where one transaction in three is disputed is not a fixture
	 * anybody is testing against.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function transaction_type(): string {
		$requested = (array) ( $this->generation_params['transaction_types'] ?? array() );
		$types     = array_values(
			array_intersect( array_filter( $requested, 'is_string' ), Status::transaction_types() )
		);

		if ( array() === $types ) {
			$types = array( Status::CHARGE, Status::REFUND );
		}

		// `include_refunds` is the endpoint's older way of saying the same thing, and still works.
		if ( isset( $this->generation_params['include_refunds'] ) && ! $this->generation_params['include_refunds'] ) {
			$types = array_values( array_diff( $types, array( Status::REFUND ) ) );
		}

		if ( array() === $types ) {
			$types = array( Status::CHARGE );
		}

		$share = isset( $this->generation_params['refund_percentage'] )
			? (float) $this->generation_params['refund_percentage']
			: 5.0;

		if ( in_array( Status::REFUND, $types, true )
			&& $this->get_faker()->boolean( (int) round( max( 0.0, min( 100.0, $share ) ) ) ) ) {
			return Status::REFUND;
		}

		// Disputes stay rare where they are wanted at all.
		if ( in_array( Status::DISPUTE, $types, true ) && $this->get_faker()->boolean( 2 ) ) {
			return Status::DISPUTE;
		}

		if ( in_array( Status::CHARGE, $types, true ) ) {
			return Status::CHARGE;
		}

		// Neither charges nor a drawn refund: whatever is left, so a run asking only for refunds
		// gets refunds rather than the charge nobody listed.
		return (string) reset( $types );
	}

	/**
	 * The canonical status for this transaction.
	 *
	 * A refund is refunded and a dispute is disputed — the type decides, because the alternative is
	 * a refund transaction marked pending, which no gateway produces and no report can read.
	 *
	 * @since 1.1.0
	 *
	 * @param string $type Canonical transaction type.
	 *
	 * @return string
	 */
	private function status( string $type ): string {
		if ( Status::REFUND === $type ) {
			return Status::REFUNDED;
		}

		if ( Status::DISPUTE === $type ) {
			return Status::DISPUTED;
		}

		$requested = (array) ( $this->generation_params['transaction_statuses'] ?? array() );
		$allowed   = array_values(
			array_intersect(
				array_filter( $requested, 'is_string' ),
				// Refunded and disputed are excluded from the charge spread: they belong to their
				// own types, and a charge marked refunded contradicts the refund beside it.
				array( Status::PENDING, Status::AUTHORIZED, Status::COMPLETED, Status::FAILED )
			)
		);

		if ( array() === $allowed ) {
			$allowed = array( Status::COMPLETED, Status::PENDING, Status::FAILED );
		}

		return (string) $this->get_faker()->randomElement( $allowed );
	}

	/**
	 * What this transaction is worth, in minor units.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function total(): int {
		$range = (array) ( $this->generation_params['amount_range'] ?? array() );
		$min   = isset( $range['min'] ) ? (float) $range['min'] : 10.0;
		$max   = isset( $range['max'] ) ? (float) $range['max'] : 1000.0;

		return (int) round(
			$this->get_faker()->randomFloat( 2, max( 0.01, min( $min, $max ) ), max( 0.01, $min, $max ) ) * 100
		);
	}

	/**
	 * Whether gateway metadata was asked for.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	private function metadata_enabled(): bool {
		if ( ! isset( $this->generation_params['include_gateway_metadata'] ) ) {
			return true;
		}

		return (bool) $this->generation_params['include_gateway_metadata'];
	}
}
