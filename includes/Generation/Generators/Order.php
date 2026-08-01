<?php
/**
 * Order Generator Class for StoreSeeder Plugin
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
		$items    = array();
		$country  = $this->country();
		$status   = $this->status();
		$subtotal = 0;

		foreach ( range( 1, $this->items_per_order() ) as $ignored ) {
			// Integer minor units. Major units make a $456.78 line render as $4.57
			// on any platform storing cents.
			$unit      = (int) round( $this->get_faker()->randomFloat( 2, 10, 500 ) * 100 );
			$quantity  = $this->get_faker()->numberBetween( 1, 2 );
			$subtotal += $unit * $quantity;

			$items[] = array(
				'unit_price' => $unit,
				'quantity'   => $quantity,
			);
		}

		$paid = in_array( $status, array( Status::COMPLETED, Status::PROCESSING, Status::REFUNDED ), true );

		return array(
			'items'          => $items,
			// A store outside a taxed jurisdiction, or one testing pre-tax reporting, asks for no
			// tax and every line stays at its own price.
			'tax_rate'       => $this->flag( 'include_tax' ) ? 0.08 : 0.0,
			// Some orders carry a coupon. Which one, and what it is worth, only the
			// platform knows.
			'use_coupon'     => $this->get_faker()->boolean( 40 ),
			// A discount on a share of orders, in minor units like every other amount. The
			// writer applies it; what it was worth is generated data.
			'discount_total' => $this->get_faker()->boolean( 30 ) ? (int) round( $subtotal * $this->get_faker()->numberBetween( 5, 25 ) / 100 ) : 0,
			// Free shipping on a share of them, which is the case that breaks totals code. A store
			// selling only downloads asks for no shipping at all, and gets none.
			'shipping_total' => $this->shipping_total(),
			'currency'       => 'USD',
			'status'         => $status,
			'payment_method' => $this->payment_method(),
			'invoice_no'     => $this->get_faker()->numberBetween( 1000, 9999 ),
			'receipt_number' => $this->get_faker()->numberBetween( 1000, 9999 ),
			// A note from the shopper on a minority of orders — the field every store's
			// packing slip prints and nothing tests.
			'customer_note'  => $this->get_faker()->boolean( 25 ) ? $this->get_faker()->sentence( 10 ) : '',
			// Where the order came from, which is what an analytics or tax report reads.
			'ip_address'     => $this->get_faker()->ipv4(),
			'user_agent'     => $this->get_faker()->userAgent(),
			// Only an order that was paid has a payment date, and only a finished one has a
			// completion date. Inventing either for a pending order makes reporting lie.
			'paid_at'        => $paid ? $this->timestamp() : null,
			'completed_at'   => Status::COMPLETED === $status ? $this->timestamp() : null,
			// A guest checkout, which is a real order every store takes and no fixture had. The
			// writer draws the customer; whether there should be one is generated data.
			'with_customer'  => $this->flag( 'include_customer' ),
			'addresses'      => array(
				'billing'  => $this->build_address( $country ),
				'shipping' => $this->build_address( $country ),
			),
			'created_at'     => current_time( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * What this order pays for shipping, in minor units.
	 *
	 * Null and zero are different orders, which is the distinction the flag exists for: zero is
	 * free shipping — a method was chosen and cost nothing — and null is an order that was never
	 * shipped, as every order in a download-only store is. A platform that models shipping as a
	 * line item shows the first and not the second.
	 *
	 * @since 1.1.0
	 *
	 * @return int|null
	 */
	private function shipping_total(): ?int {
		if ( ! $this->flag( 'include_shipping' ) ) {
			return null;
		}

		return $this->get_faker()->boolean( 25 ) ? 0 : (int) round( $this->get_faker()->randomFloat( 2, 3, 25 ) * 100 );
	}

	/**
	 * Read an include flag, which defaults to on.
	 *
	 * The three `include_*` parameters have been declared since the first release and read by
	 * nothing, so every order carried shipping, tax and a customer whatever was asked for.
	 *
	 * @since 1.1.0
	 *
	 * @param string $name Parameter name.
	 *
	 * @return bool
	 */
	private function flag( string $name ): bool {
		if ( ! isset( $this->generation_params[ $name ] ) ) {
			return true;
		}

		return (bool) $this->generation_params[ $name ];
	}

	/**
	 * How many line items this order carries.
	 *
	 * `items_per_order` has been a declared parameter with a min and a max since the beginning,
	 * and was read by nothing — every order got one to three items regardless.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function items_per_order(): int {
		$range = (array) ( $this->generation_params['items_per_order'] ?? array() );
		$min   = isset( $range['min'] ) ? (int) $range['min'] : 1;
		$max   = isset( $range['max'] ) ? (int) $range['max'] : 3;

		if ( $max < $min ) {
			$max = $min;
		}

		return $this->get_faker()->numberBetween( max( 1, $min ), max( 1, $max ) );
	}

	/**
	 * The canonical status for this order.
	 *
	 * `order_status` names one status or `mixed`. It was declared and ignored, so asking for a
	 * store full of completed orders produced the same spread as asking for anything else.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function status(): string {
		$requested = $this->generation_params['order_status'] ?? null;
		$spread    = array( Status::COMPLETED, Status::PROCESSING, Status::ON_HOLD, Status::CANCELLED, Status::FAILED );

		// Either shape, because both surfaces have been sending different ones: the REST schema
		// declared an array of statuses to draw from and the admin a single string with `mixed`.
		// They agree now, and this still accepts both so an existing caller is not broken.
		$allowed = is_array( $requested )
			? array_values( array_filter( $requested, 'is_string' ) )
			: array_filter( array( (string) $requested ) );

		// Anything the canonical vocabulary does not know is dropped rather than written as a
		// status no platform can map.
		$allowed = array_values( array_intersect( $allowed, Status::order_statuses() ) );

		if ( array() === $allowed ) {
			return (string) $this->get_faker()->randomElement( $spread );
		}

		return (string) $this->get_faker()->randomElement( $allowed );
	}

	/**
	 * One of the requested payment methods.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function payment_method(): string {
		$methods = (array) ( $this->generation_params['payment_methods'] ?? array() );
		$methods = array_values( array_filter( $methods, 'is_string' ) );

		if ( array() === $methods ) {
			$methods = array( 'stripe', 'paypal', 'cod' );
		}

		return (string) $this->get_faker()->randomElement( $methods );
	}

	/**
	 * The country this order ships to.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function country(): string {
		$geo       = (array) ( $this->generation_params['geographical_distribution'] ?? array() );
		$countries = array_values( array_filter( (array) ( $geo['countries'] ?? array() ), 'is_string' ) );

		if ( array() === $countries ) {
			$countries = array( 'US' );
		}

		return strtoupper( (string) $this->get_faker()->randomElement( $countries ) );
	}

	/**
	 * A datetime inside the last ninety days.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function timestamp(): string {
		return gmdate( 'Y-m-d H:i:s', time() - $this->get_faker()->numberBetween( 0, 90 ) * DAY_IN_SECONDS );
	}

	/**
	 * Build one address for the order.
	 *
	 * @since 1.1.0
	 *
	 * @param string $country Two-letter country code the address belongs to.
	 *
	 * @return array<string, string> Canonical address fields.
	 */
	private function build_address( string $country = 'US' ): array {
		return array(
			'name'      => $this->get_faker()->name(),
			// A company on a minority of addresses. Both platforms store one, and an order with
			// a company name is what a B2B invoice template needs to be tested against.
			'company'   => $this->get_faker()->boolean( 20 ) ? $this->get_faker()->company() : '',
			'address_1' => $this->get_faker()->streetAddress(),
			'address_2' => $this->get_faker()->optional( 0.3 )->secondaryAddress() ?? '',
			'city'      => $this->get_faker()->city(),
			// A US state abbreviation on a French address is the kind of detail that gives a
			// generated order away, so it only accompanies the country it belongs to.
			'state'     => 'US' === $country ? $this->get_faker()->stateAbbr() : '',
			'postcode'  => $this->get_faker()->postcode(),
			'country'   => $country,
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
