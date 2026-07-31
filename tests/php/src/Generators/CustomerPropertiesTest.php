<?php
/**
 * Tests for the customer properties, and for the parameters that used to do nothing.
 *
 * Customers were the worst case of the three surfaces disagreeing. The endpoint declared
 * `customer_type` — singular, enumerating individual/business/mixed — while the admin and the MCP
 * ability declared `customer_types` with five entirely different values, and nothing read either.
 * `country_focus`, `demographics`, `loyalty_tier_focus` and `account_status` were the same story.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generators;

use StoreSeeder\Generation\Generators\Customer;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generators\Customer
 */
class CustomerPropertiesTest extends StoreSeederUnitTestCase {

	/**
	 * Entity assertions need neither the REST server nor a database fixture.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	/**
	 * The generator under test.
	 *
	 * @var Customer
	 */
	private Customer $generator;

	public function setUp(): void {
		parent::setUp();

		$this->generator = new Customer();
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
			array( 'first_name', 'last_name', 'email', 'billing_address', 'meta', 'customer_type', 'with_account', 'date_created', 'currency' ) as $field
		) {
			$this->assertArrayHasKey( $field, $entity, $field );
		}
	}

	/**
	 * The account is as old as the customer. `customer_since` was metadata on a row created today,
	 * so a customer "since 2021" registered this morning and every cohort report disagreed with the
	 * profile screen.
	 */
	public function test_the_creation_date_matches_the_customer_since_date(): void {
		foreach ( range( 1, 10 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertSame( $entity['meta']['customer_since'], $entity['date_created'] );
			$this->assertLessThan( time(), strtotime( (string) $entity['date_created'] ) );
		}
	}

	/**
	 * Lifetime spend is an integer in minor units. A float here was written straight into Fluent
	 * Cart's `ltv`, which is a BIGINT of cents — so $1,234.56 was truncated to 1234 and shown in the
	 * admin as $12.34.
	 */
	public function test_the_lifetime_totals_are_minor_units(): void {
		foreach ( range( 1, 25 ) as $ignored ) {
			$meta = $this->entity()['meta'];

			$this->assertIsInt( $meta['total_spent'] );
			$this->assertIsInt( $meta['average_order_value'] );
			$this->assertGreaterThanOrEqual( 0, $meta['total_spent'] );

			if ( $meta['total_orders'] > 0 ) {
				// The average is the total over the orders, in the same unit as the total.
				$this->assertSame(
					(int) round( $meta['total_spent'] / $meta['total_orders'] ),
					$meta['average_order_value']
				);
			}
		}
	}

	/**
	 * A first purchase cannot follow the last one. Fluent Cart stores both dates, and a pair the
	 * wrong way round makes a customer look like a single visit that lasted two years.
	 */
	public function test_the_first_purchase_precedes_the_last(): void {
		$found = false;

		foreach ( range( 1, 40 ) as $ignored ) {
			$meta = $this->entity()['meta'];

			if ( null === $meta['first_order_date'] ) {
				$this->assertNull( $meta['last_order_date'] );
				continue;
			}

			$found = true;

			$this->assertLessThanOrEqual(
				strtotime( (string) $meta['last_order_date'] ),
				strtotime( (string) $meta['first_order_date'] )
			);
		}

		$this->assertTrue( $found, 'No customer in forty had bought anything.' );
	}

	// -----------------------------------------------------------------------------------
	// The parameters that used to be ignored.
	// -----------------------------------------------------------------------------------

	public function test_the_country_focus_is_honoured(): void {
		$this->with( array( 'country_focus' => array( 'de', 'FR' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			// Upper-cased: a two-letter code is what every platform stores, and `de` matches
			// nothing in a country list.
			$this->assertContains( $this->entity()['billing_address']['country'], array( 'DE', 'FR' ) );
		}
	}

	public function test_the_age_groups_are_honoured(): void {
		$this->with( array( 'demographics' => array( 'age_groups' => array( '65+' ) ) ) );

		$checked = 0;

		foreach ( range( 1, 40 ) as $ignored ) {
			$birth_date = $this->entity()['meta']['birth_date'];

			// A third of customers give no birth date, which is the realistic case.
			if ( null === $birth_date ) {
				continue;
			}

			++$checked;

			$age = (int) floor( ( time() - strtotime( (string) $birth_date ) ) / YEAR_IN_SECONDS );

			$this->assertGreaterThanOrEqual( 65, $age );
		}

		$this->assertGreaterThan( 0, $checked );
	}

	public function test_a_loyalty_tier_focus_overrides_the_spend_derived_tier(): void {
		$this->with( array( 'loyalty_tier_focus' => array( 'platinum' ) ) );

		foreach ( range( 1, 30 ) as $ignored ) {
			// Including the customers who have bought nothing: that branch used to hardcode
			// bronze, so a platinum-only run quietly produced bronze customers.
			$this->assertSame( 'platinum', $this->entity()['meta']['loyalty_tier'] );
		}
	}

	public function test_an_unknown_tier_falls_back_to_the_spend_derived_one(): void {
		$this->with( array( 'loyalty_tier_focus' => array( 'diamond' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertContains(
				$this->entity()['meta']['loyalty_tier'],
				array( 'bronze', 'silver', 'gold', 'platinum' )
			);
		}
	}

	public function test_the_account_status_is_honoured(): void {
		$this->with( array( 'account_status' => 'inactive' ) );

		foreach ( range( 1, 15 ) as $ignored ) {
			$this->assertSame( 'inactive', $this->entity()['meta']['account_status'] );
		}
	}

	public function test_mixed_spreads_across_the_statuses(): void {
		$this->with( array( 'account_status' => 'mixed' ) );

		$seen = array();

		foreach ( range( 1, 60 ) as $ignored ) {
			$seen[ $this->entity()['meta']['account_status'] ] = true;
		}

		$this->assertCount( 3, $seen );

		foreach ( array_keys( $seen ) as $status ) {
			$this->assertContains( $status, array( 'active', 'inactive', 'pending' ) );
		}
	}

	public function test_a_guest_never_holds_an_account(): void {
		$this->with( array( 'customer_types' => array( 'guest' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertSame( 'guest', $entity['customer_type'] );
			$this->assertFalse( $entity['with_account'] );
		}
	}

	public function test_a_wholesale_customer_always_carries_a_company(): void {
		$this->with( array( 'customer_types' => array( 'wholesale' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertNotEmpty( $this->entity()['billing_address']['company'] );
		}
	}

	public function test_a_vip_is_flagged_as_one(): void {
		$this->with( array( 'customer_types' => array( 'vip' ) ) );

		foreach ( range( 1, 15 ) as $ignored ) {
			$this->assertTrue( $this->entity()['meta']['vip_status'] );
		}
	}

	/**
	 * A returning customer has returned, so the age-weighted coin toss that decides whether anybody
	 * has bought anything does not apply to one.
	 */
	public function test_a_returning_customer_has_bought_something(): void {
		$this->with( array( 'customer_types' => array( 'returning' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$meta = $this->entity()['meta'];

			$this->assertGreaterThan( 0, $meta['total_orders'] );
			$this->assertGreaterThan( 0, $meta['total_spent'] );
			$this->assertNotNull( $meta['last_order_date'] );
		}
	}

	public function test_an_unknown_segment_falls_back_to_the_default_mix(): void {
		$this->with( array( 'customer_types' => array( 'individual', 'business' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertContains( $this->entity()['customer_type'], array( 'regular', 'returning' ) );
		}
	}

	/**
	 * Two names for one switch, because two surfaces shipped different ones: the endpoint declares
	 * `include_history` and the admin `purchase_history.simulate_history`. Either turns it off.
	 */
	public function test_history_can_be_switched_off_from_either_name(): void {
		foreach (
			array(
				array( 'include_history' => false ),
				array( 'purchase_history' => array( 'simulate_history' => false ) ),
			) as $params
		) {
			$this->with( $params );

			foreach ( range( 1, 10 ) as $ignored ) {
				$meta = $this->entity()['meta'];

				$this->assertSame( 0, $meta['total_orders'] );
				$this->assertSame( 0, $meta['total_spent'] );
				$this->assertNull( $meta['last_order_date'] );
			}
		}
	}

	public function test_the_shipping_address_ratio_is_honoured(): void {
		$this->with( array( 'address_preferences' => array( 'different_addresses_ratio' => 100 ) ) );

		foreach ( range( 1, 15 ) as $ignored ) {
			$this->assertNotSame( array(), $this->entity()['shipping_address'] );
		}

		$this->with( array( 'address_preferences' => array( 'different_addresses_ratio' => 0 ) ) );

		foreach ( range( 1, 15 ) as $ignored ) {
			// An empty array means "same as billing", which every writer already handles.
			$this->assertSame( array(), $this->entity()['shipping_address'] );
		}
	}

	public function test_the_shipping_address_can_be_switched_off(): void {
		$this->with(
			array(
				'address_preferences' => array(
					'include_shipping'          => false,
					'different_addresses_ratio' => 100,
				),
			)
		);

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertSame( array(), $this->entity()['shipping_address'] );
		}
	}

	public function test_phone_numbers_can_be_switched_off(): void {
		$this->with( array( 'contact_preferences' => array( 'phone_numbers' => false ) ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			// Empty rather than absent, so every writer keeps the same shape.
			$this->assertSame( '', $this->entity()['billing_address']['phone'] );
		}
	}

	public function test_the_marketing_opt_in_ratio_is_honoured(): void {
		$this->with( array( 'contact_preferences' => array( 'marketing_opt_in_ratio' => 100 ) ) );

		foreach ( range( 1, 15 ) as $ignored ) {
			$this->assertTrue( $this->entity()['meta']['customer_preferences']['marketing_opt_in'] );
		}

		$this->with( array( 'contact_preferences' => array( 'marketing_opt_in_ratio' => 0 ) ) );

		foreach ( range( 1, 15 ) as $ignored ) {
			$this->assertFalse( $this->entity()['meta']['customer_preferences']['marketing_opt_in'] );
		}
	}

	/**
	 * The generator still names no platform, which the added fields are the most likely thing to
	 * break: `ltv`, `aov` and `_money_spent` are each one platform's word for a shared idea.
	 */
	public function test_the_entity_names_no_platform(): void {
		$serialised = (string) wp_json_encode( $this->entity() );

		foreach ( array( 'ltv', 'aov', '_money_spent', 'purchase_value', 'user_registered' ) as $word ) {
			$this->assertStringNotContainsString( $word, $serialised, $word );
		}
	}
}
