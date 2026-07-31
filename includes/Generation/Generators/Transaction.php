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
		$methods  = array( 'stripe', 'paypal', 'bank_transfer', 'cod' );
		$statuses = array( 'succeeded', 'pending', 'failed', 'refunded' );
		// Charge, refund and dispute are the three every gateway models. A seeder has
		// no business inventing others: the Refund generator filters on charge, so a
		// transaction with an unrecognised type is invisible to it.
		$types = array( 'charge', 'refund', 'dispute' );

		$payment_method = $this->get_faker()->randomElement( $methods );
		$status         = $this->get_faker()->randomElement( $statuses );

		$entity = array(
			'vendor_charge_id'    => strtoupper( $this->get_faker()->bothify( 'CH-##########' ) ),
			'payment_method'      => $payment_method,
			'payment_mode'        => 'live',
			'payment_method_type' => $payment_method,
			'currency'            => 'USD',
			'transaction_type'    => $this->get_faker()->randomElement( $types ),
			'status'              => $status,
			// Integer minor units. Major units here render a hundred times small on
			// any platform that stores cents.
			'total'               => (int) round( $this->get_faker()->randomFloat( 2, 10, 1000 ) * 100 ),
			'rate'                => 1.0,
			'payer_email'         => $this->get_faker()->email(),
		);

		// Card details only exist for a card payment that actually went through.
		if ( in_array( $payment_method, array( 'stripe', 'paypal' ), true ) && 'succeeded' === $status ) {
			$entity['card_last_4'] = $this->get_faker()->numberBetween( 1000, 9999 );
			$entity['card_brand']  = $this->get_faker()->randomElement( array( 'visa', 'mastercard', 'amex', 'discover' ) );
		}

		return $entity;
	}
}
