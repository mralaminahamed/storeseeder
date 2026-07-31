<?php
/**
 * Order Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder\Generators\Resources
 */

namespace StoreSeeder\Generators\Resources;

use StoreSeeder\Generators\Generator;
use StoreSeeder\Platforms\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Order Generator Class
 *
 * Shapes orders: how many line items at what price and quantity, the status, the
 * payment method, the addresses. What it deliberately does not do is arithmetic on
 * money it cannot see -- line items point at real product variations, so the subtotal,
 * the tax and any coupon discount are computed by the writer once it knows which
 * products it actually found.
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
			'orders' => __( 'Orders', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates complete orders with customer data, items, payments, and shipping for testing order processing.';
	}

	/**
	 * Build a canonical order
	 *
	 * The item list is drawn at full length; the writer pairs as many entries as it
	 * finds real products for, then totals what remains. `tax_rate` is here rather
	 * than in the writer so the number stays generated data and only the arithmetic
	 * moves.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		$items       = array();
		$items_count = $this->get_faker()->numberBetween( 1, 3 );

		for ( $i = 0; $i < $items_count; $i++ ) {
			$items[] = array(
				// Integer minor units. Major units make a $456.78 line render as $4.57
				// on any platform storing cents.
				'unit_price' => (int) round( $this->get_faker()->randomFloat( 2, 10, 500 ) * 100 ),
				'quantity'   => $this->get_faker()->numberBetween( 1, 2 ),
			);
		}

		return array(
			'items'          => $items,
			'tax_rate'       => 0.08,
			// Some orders carry a coupon. Which one, and what it is worth, only the
			// platform knows.
			'use_coupon'     => $this->get_faker()->boolean( 40 ),
			'currency'       => 'USD',
			'status'         => $this->get_faker()->randomElement(
				array( Status::COMPLETED, Status::PROCESSING, Status::ON_HOLD, Status::CANCELLED, Status::FAILED )
			),
			'payment_method' => $this->get_faker()->randomElement( array( 'stripe', 'paypal', 'cod' ) ),
			'invoice_no'     => $this->get_faker()->numberBetween( 1000, 9999 ),
			'receipt_number' => $this->get_faker()->numberBetween( 1000, 9999 ),
			'addresses'      => array(
				'billing'  => $this->build_address(),
				'shipping' => $this->build_address(),
			),
			'created_at'     => current_time( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Build one address for the order.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string> Canonical address fields.
	 */
	private function build_address(): array {
		return array(
			'name'      => $this->get_faker()->name(),
			'address_1' => $this->get_faker()->streetAddress(),
			'address_2' => $this->get_faker()->optional( 0.3 )->secondaryAddress() ?? '',
			'city'      => $this->get_faker()->city(),
			'state'     => $this->get_faker()->stateAbbr(),
			'postcode'  => $this->get_faker()->postcode(),
			'country'   => 'US',
			'phone'     => $this->get_faker()->phoneNumber(),
		);
	}

	/**
	 * Preview columns for orders
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, array{key: string, label: string}>
	 */
	protected function get_preview_columns(): array {
		return array(
			array(
				'key'   => 'number',
				'label' => __( 'Order', 'storeseeder' ),
			),
			array(
				'key'   => 'customer',
				'label' => __( 'Customer', 'storeseeder' ),
			),
			array(
				'key'   => 'items',
				'label' => __( 'Items', 'storeseeder' ),
			),
			array(
				'key'   => 'total',
				'label' => __( 'Total', 'storeseeder' ),
			),
			array(
				'key'   => 'status',
				'label' => __( 'Status', 'storeseeder' ),
			),
		);
	}

	/**
	 * Build an order preview row
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, array{v: mixed, kind: string}>
	 */
	protected function build_preview_row(): array {
		$faker = $this->get_faker();

		return array(
			'number'   => array(
				'v'    => '#' . $faker->numberBetween( 1000, 99999 ),
				'kind' => 'mono',
			),
			'customer' => array(
				'v'    => $faker->firstName() . ' ' . $faker->lastName(),
				'kind' => 'text',
			),
			'items'    => array(
				'v'    => $faker->numberBetween( 1, 8 ),
				'kind' => 'num',
			),
			'total'    => array(
				'v'    => '$' . number_format( $faker->randomFloat( 2, 10, 2000 ), 2 ),
				'kind' => 'money',
			),
			'status'   => array(
				'v'    => $faker->randomElement( array( 'completed', 'processing', 'on-hold', 'refunded', 'failed' ) ),
				'kind' => 'status',
			),
		);
	}
}
