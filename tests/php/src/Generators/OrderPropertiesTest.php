<?php
/**
 * Tests for the order properties, and for the parameters that used to do nothing.
 *
 * The Orders generator declared seven parameter groups and read none of them. Asking for a store
 * full of completed orders produced the same spread as asking for anything else, and every order
 * had one to three items regardless of the range the admin offered. These tests exist so that
 * cannot come back — a parameter that is declared has to change the output, or it should not be
 * declared.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generators;

use StoreSeeder\Generation\Generators\Order;
use StoreSeeder\Platforms\Status;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generators\Order
 */
class OrderPropertiesTest extends StoreSeederUnitTestCase {

	/**
	 * Entity assertions need neither the REST server nor a database fixture.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	/**
	 * The generator under test.
	 *
	 * @var Order
	 */
	private Order $generator;

	public function setUp(): void {
		parent::setUp();

		$this->generator = new Order();
		$this->generator->set_locale( 'en_US' );
		$this->generator->set_faker();
	}

	/**
	 * Build one entity through the generator's own path.
	 *
	 * @return array<string, mixed>
	 */
	private function entity(): array {
		$method = new \ReflectionMethod( $this->generator, 'build_entity' );

		return (array) $method->invoke( $this->generator );
	}

	/**
	 * @param array<string, mixed> $params Generation parameters.
	 */
	private function with( array $params ): void {
		$this->generator->set_generation_params( $params );
	}

	// -----------------------------------------------------------------------------------
	// The fields every platform stores.
	// -----------------------------------------------------------------------------------

	public function test_the_entity_carries_the_unified_fields(): void {
		$entity = $this->entity();

		foreach (
			array(
				'items',
				'tax_rate',
				'discount_total',
				'shipping_total',
				'currency',
				'status',
				'payment_method',
				'customer_note',
				'ip_address',
				'user_agent',
				'paid_at',
				'completed_at',
				'addresses',
				'created_at',
			) as $field
		) {
			$this->assertArrayHasKey( $field, $entity, $field );
		}
	}

	/**
	 * Money stays an integer in the currency's minor unit. Two new amounts arrived on the order at
	 * once, and 6.75 and 675 both look plausible in a shipping column.
	 */
	public function test_every_money_field_is_minor_units(): void {
		foreach ( range( 1, 20 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertIsInt( $entity['discount_total'] );
			$this->assertIsInt( $entity['shipping_total'] );
			$this->assertGreaterThanOrEqual( 0, $entity['discount_total'] );
			$this->assertGreaterThanOrEqual( 0, $entity['shipping_total'] );

			foreach ( $entity['items'] as $item ) {
				$this->assertIsInt( $item['unit_price'] );
			}
		}
	}

	/**
	 * A discount can only be worth a share of the order, never more than it. A discount larger than
	 * the subtotal is a negative total, which every platform stores and no report survives.
	 */
	public function test_a_discount_never_exceeds_the_subtotal(): void {
		$found = false;

		foreach ( range( 1, 40 ) as $ignored ) {
			$entity   = $this->entity();
			$subtotal = 0;

			foreach ( $entity['items'] as $item ) {
				$subtotal += $item['unit_price'] * $item['quantity'];
			}

			if ( $entity['discount_total'] > 0 ) {
				$found = true;
			}

			$this->assertLessThan( $subtotal, $entity['discount_total'] );
		}

		$this->assertTrue( $found, 'No order in forty carried a discount.' );
	}

	/**
	 * Free shipping is a real outcome, not an accident — it is the case that breaks totals code, so
	 * the fixture has to produce it.
	 */
	public function test_free_shipping_occurs(): void {
		$free = 0;

		foreach ( range( 1, 60 ) as $ignored ) {
			if ( 0 === $this->entity()['shipping_total'] ) {
				++$free;
			}
		}

		$this->assertGreaterThan( 0, $free );
		$this->assertLessThan( 60, $free );
	}

	/**
	 * A payment date belongs to an order that was paid, and a completion date to one that finished.
	 * Inventing either for a pending order makes every revenue report lie.
	 */
	public function test_dates_follow_the_status(): void {
		$paid_statuses = array( Status::COMPLETED, Status::PROCESSING, Status::REFUNDED );

		foreach ( range( 1, 60 ) as $ignored ) {
			$entity = $this->entity();

			if ( in_array( $entity['status'], $paid_statuses, true ) ) {
				$this->assertNotNull( $entity['paid_at'], $entity['status'] );
			} else {
				$this->assertNull( $entity['paid_at'], $entity['status'] );
			}

			if ( Status::COMPLETED === $entity['status'] ) {
				$this->assertNotNull( $entity['completed_at'] );
			} else {
				$this->assertNull( $entity['completed_at'], $entity['status'] );
			}
		}
	}

	public function test_both_addresses_carry_the_unified_fields(): void {
		$entity = $this->entity();

		foreach ( array( 'billing', 'shipping' ) as $type ) {
			foreach (
				array( 'name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ) as $field
			) {
				$this->assertArrayHasKey( $field, $entity['addresses'][ $type ], $type . '.' . $field );
			}
		}
	}

	// -----------------------------------------------------------------------------------
	// The parameters that used to be ignored.
	// -----------------------------------------------------------------------------------

	public function test_the_item_range_is_honoured(): void {
		$this->with(
			array(
				'items_per_order' => array(
					'min' => 4,
					'max' => 6,
				),
			)
		);

		foreach ( range( 1, 25 ) as $ignored ) {
			$count = count( $this->entity()['items'] );

			$this->assertGreaterThanOrEqual( 4, $count );
			$this->assertLessThanOrEqual( 6, $count );
		}
	}

	/**
	 * A maximum below the minimum is a request nobody meant, and FakerPHP throws on it rather than
	 * choosing.
	 */
	public function test_an_inverted_item_range_does_not_fail(): void {
		$this->with(
			array(
				'items_per_order' => array(
					'min' => 5,
					'max' => 2,
				),
			)
		);

		$this->assertCount( 5, $this->entity()['items'] );
	}

	/**
	 * The headline bug: `order_status` was declared on both surfaces and read by neither.
	 */
	public function test_a_requested_status_is_the_only_one_produced(): void {
		$this->with( array( 'order_status' => array( Status::COMPLETED ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertSame( Status::COMPLETED, $this->entity()['status'] );
		}
	}

	/**
	 * Both shapes, because the two surfaces spent a release sending different ones — the REST schema
	 * an array and the admin a single string. They agree now and both still work.
	 */
	public function test_a_status_string_is_accepted_as_well_as_a_list(): void {
		$this->with( array( 'order_status' => Status::ON_HOLD ) );

		$this->assertSame( Status::ON_HOLD, $this->entity()['status'] );
	}

	/**
	 * A status outside the canonical vocabulary is dropped rather than written as something no
	 * platform can map. Dropping every entry falls back to the spread, which is a store with orders
	 * in it rather than a run that fails.
	 */
	public function test_an_unknown_status_falls_back_to_the_spread(): void {
		$this->with( array( 'order_status' => array( 'wc-completed', 'mixed' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertContains( $this->entity()['status'], Status::order_statuses() );
		}
	}

	public function test_a_known_status_survives_an_unknown_one_beside_it(): void {
		$this->with( array( 'order_status' => array( 'nonsense', Status::REFUNDED ) ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertSame( Status::REFUNDED, $this->entity()['status'] );
		}
	}

	public function test_the_status_spread_covers_the_canonical_vocabulary(): void {
		$seen = array();

		foreach ( range( 1, 120 ) as $ignored ) {
			$seen[ $this->entity()['status'] ] = true;
		}

		foreach ( array_keys( $seen ) as $status ) {
			$this->assertContains( $status, Status::order_statuses(), $status );
		}

		// More than one, or the spread is not a spread.
		$this->assertGreaterThan( 1, count( $seen ) );
	}

	/**
	 * Zero and null are different orders: zero is free shipping, where a method was chosen and cost
	 * nothing, and null is an order that was never shipped at all. A platform modelling shipping as
	 * a line item shows the first and not the second, so the entity has to keep them apart.
	 */
	public function test_shipping_can_be_switched_off_entirely(): void {
		$this->with( array( 'include_shipping' => false ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertNull( $this->entity()['shipping_total'] );
		}
	}

	public function test_tax_can_be_switched_off(): void {
		$this->with( array( 'include_tax' => false ) );

		$this->assertSame( 0.0, $this->entity()['tax_rate'] );

		$this->with( array( 'include_tax' => true ) );

		$this->assertGreaterThan( 0, $this->entity()['tax_rate'] );
	}

	/**
	 * Whether an order has a customer is generated data; which customer is the writer's, since only
	 * the platform knows who exists.
	 */
	public function test_a_guest_order_can_be_asked_for(): void {
		$this->assertTrue( $this->entity()['with_customer'] );

		$this->with( array( 'include_customer' => false ) );

		$this->assertFalse( $this->entity()['with_customer'] );
	}

	/**
	 * All three default to on, so an existing caller that sends none of them gets what it always
	 * got — a taxed, shipped order belonging to somebody.
	 */
	public function test_the_include_flags_default_to_on(): void {
		$entity = $this->entity();

		$this->assertTrue( $entity['with_customer'] );
		$this->assertIsInt( $entity['shipping_total'] );
		$this->assertGreaterThan( 0, $entity['tax_rate'] );
	}

	public function test_the_payment_methods_are_honoured(): void {
		$this->with( array( 'payment_methods' => array( 'paypal', 'cod' ) ) );

		foreach ( range( 1, 25 ) as $ignored ) {
			$this->assertContains( $this->entity()['payment_method'], array( 'paypal', 'cod' ) );
		}
	}

	public function test_the_geographical_distribution_is_honoured(): void {
		$this->with( array( 'geographical_distribution' => array( 'countries' => array( 'de', 'FR' ) ) ) );

		foreach ( range( 1, 25 ) as $ignored ) {
			$entity = $this->entity();

			// Upper-cased, since a two-letter code is what every platform stores and `de` would
			// match nothing in a country list.
			$this->assertContains( $entity['addresses']['billing']['country'], array( 'DE', 'FR' ) );

			// Both addresses belong to the same country. An order billed in France and shipped to
			// Germany is legitimate and not what a distribution setting asks for.
			$this->assertSame(
				$entity['addresses']['billing']['country'],
				$entity['addresses']['shipping']['country']
			);
		}
	}

	/**
	 * A US state abbreviation on a French address is the detail that gives a generated order
	 * away.
	 */
	public function test_a_state_only_accompanies_the_country_it_belongs_to(): void {
		$this->with( array( 'geographical_distribution' => array( 'countries' => array( 'FR' ) ) ) );

		$this->assertSame( '', $this->entity()['addresses']['billing']['state'] );

		$this->with( array( 'geographical_distribution' => array( 'countries' => array( 'US' ) ) ) );

		$this->assertNotSame( '', $this->entity()['addresses']['billing']['state'] );
	}

	/**
	 * The generator still names no platform, which the added fields are the most likely thing to
	 * break — `manual_discount_total` and `customer_ip_address` are each one platform's word for a
	 * shared idea.
	 */
	public function test_the_entity_names_no_platform(): void {
		$serialised = (string) wp_json_encode( $this->entity() );

		foreach (
			array( 'manual_discount_total', 'customer_ip_address', 'wc-', 'shop_order', 'fct_' ) as $word
		) {
			$this->assertStringNotContainsString( $word, $serialised, $word );
		}
	}
}
