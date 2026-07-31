<?php
/**
 * Subscription Generator Class for StoreSeeder Plugin
 *
 * @since      2.4.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription Generator Class
 *
 * Shapes subscription records. The parent order, its customer and the subscribed
 * variation are existing rows the writer draws.
 *
 * Whether these rows can be *billed* is a platform question, not a generator one:
 * Fluent Cart ships the table in core but bills through Pro, WooCommerce has no
 * subscription concept until WooCommerce Subscriptions is active, and StoreEngine gates
 * it behind an addon. The capability matrix answers that; this class does not.
 */
class Subscription extends Generator {


	/**
	 * Billing intervals.
	 *
	 * @var string[]
	 */
	private const INTERVALS = array( 'daily', 'weekly', 'monthly', 'quarterly', 'half_yearly', 'yearly' );

	/**
	 * Subscription statuses worth generating.
	 *
	 * A subscription's lifecycle is its own vocabulary, not the order one in
	 * Platform\Status — a subscription can be trialing or past due, and an order cannot.
	 *
	 * @var string[]
	 */
	private const STATUSES = array( 'active', 'trialing', 'paused', 'past_due', 'canceled', 'expired' );

	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'subscription';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'subscriptions' => __( 'Subscriptions', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates subscription records against existing orders for testing recurring-billing views.';
	}

	/**
	 * Build a canonical subscription
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		// Integer minor units throughout.
		$recurring_amount = (int) round( $this->get_faker()->randomFloat( 2, 5, 200 ) * 100 );
		$recurring_tax    = (int) round( $recurring_amount * 0.08 );
		$signup_fee       = $this->get_faker()->boolean( 30 ) ? (int) round( $this->get_faker()->randomFloat( 2, 5, 50 ) * 100 ) : 0;

		$interval   = $this->get_faker()->randomElement( self::INTERVALS );
		$trial_days = $this->get_faker()->boolean( 25 ) ? $this->get_faker()->numberBetween( 3, 30 ) : 0;

		return array(
			'quantity'               => 1,
			'billing_interval'       => $interval,
			'signup_fee'             => $signup_fee,
			'recurring_amount'       => $recurring_amount,
			'recurring_tax_total'    => $recurring_tax,
			'recurring_total'        => $recurring_amount + $recurring_tax,
			'bill_times'             => $this->get_faker()->randomElement( array( 0, 6, 12, 24 ) ),
			'bill_count'             => $this->get_faker()->numberBetween( 0, 6 ),
			'trial_days'             => $trial_days,
			'collection_method'      => 'automatic',
			'status'                 => $this->get_faker()->randomElement( self::STATUSES ),
			'next_billing_date'      => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
			'current_payment_method' => $this->get_faker()->randomElement( array( 'stripe', 'paypal' ) ),
		);
	}
}
