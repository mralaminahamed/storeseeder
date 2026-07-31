<?php
/**
 * Tests for the coupon properties, and for the parameters that used to do nothing.
 *
 * The Coupons generator declared five parameter groups and read none. `discount_range` was the worst
 * of them: the admin offered a percentage band and a fixed-amount band, and every coupon came out
 * between 5 and 50 percent or 5 and 100 dollars regardless. The two type vocabularies also
 * disagreed — the endpoint accepted `fixed_amount`, which is not a type any platform has.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generators;

use StoreSeeder\Generation\Generators\Coupon;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generators\Coupon
 */
class CouponPropertiesTest extends StoreSeederUnitTestCase {

	/**
	 * Entity assertions need neither the REST server nor a database fixture.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	/**
	 * The generator under test.
	 *
	 * @var Coupon
	 */
	private Coupon $generator;

	public function setUp(): void {
		parent::setUp();

		$this->generator = new Coupon();
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
				'code',
				'discount',
				'type',
				'description',
				'usage_limit',
				'usage_limit_per_user',
				'status',
				'starts_at',
				'expires_at',
				'minimum_amount',
				'maximum_amount',
				'exclude_sale_items',
				'stackable',
				'product_count',
			) as $field
		) {
			$this->assertArrayHasKey( $field, $entity, $field );
		}
	}

	/**
	 * A percentage is a percent and a fixed amount is minor units — the one place a coupon can go
	 * wrong by two orders of magnitude, since 50 is a plausible value in either reading.
	 */
	public function test_a_percentage_is_a_percent_and_a_fixed_amount_is_minor_units(): void {
		$this->with( array( 'discount_types' => array( 'percentage' ) ) );

		foreach ( range( 1, 15 ) as $ignored ) {
			$discount = $this->entity()['discount'];

			$this->assertIsInt( $discount );
			$this->assertGreaterThanOrEqual( 5, $discount );
			$this->assertLessThanOrEqual( 50, $discount );
		}

		$this->with(
			array(
				'discount_types' => array( 'fixed' ),
				'discount_range' => array(
					'min_fixed' => 40,
					'max_fixed' => 45,
				),
			)
		);

		foreach ( range( 1, 15 ) as $ignored ) {
			$discount = $this->entity()['discount'];

			$this->assertIsInt( $discount );
			$this->assertGreaterThanOrEqual( 4000, $discount );
			$this->assertLessThanOrEqual( 4500, $discount );
		}
	}

	public function test_free_shipping_carries_no_amount(): void {
		$this->with( array( 'discount_types' => array( 'free_shipping' ) ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertSame( 'free_shipping', $entity['type'] );
			$this->assertSame( 0, $entity['discount'] );
		}
	}

	/**
	 * Null is an unlimited coupon and zero is one exhausted before its first use. Storing the second
	 * where the first was meant makes every coupon in the store refuse itself.
	 */
	public function test_switching_usage_limits_off_leaves_them_null(): void {
		$this->with( array( 'usage_limits' => array( 'set_usage_limits' => false ) ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertNull( $entity['usage_limit'] );
			$this->assertNull( $entity['usage_limit_per_user'] );
		}
	}

	/**
	 * Fluent Cart rejects a coupon whose per-customer limit exceeds its total limit, and it is
	 * nonsense on any platform, so the generator does not produce one. The total is also drawn once:
	 * capping against a second draw would cap against a limit the coupon does not carry.
	 */
	public function test_the_per_customer_limit_never_exceeds_the_total(): void {
		$this->with(
			array(
				'usage_limits' => array(
					'max_uses'          => 3,
					'max_uses_per_user' => 10,
				),
			)
		);

		foreach ( range( 1, 30 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertLessThanOrEqual( $entity['usage_limit'], $entity['usage_limit_per_user'] );
			$this->assertGreaterThanOrEqual( 1, $entity['usage_limit_per_user'] );
		}
	}

	/**
	 * A coupon that has not started yet is the case a checkout test needs, and no fixture had one.
	 */
	public function test_some_coupons_have_not_started_yet(): void {
		$scheduled = 0;
		$now       = time();

		foreach ( range( 1, 60 ) as $ignored ) {
			$entity = $this->entity();

			if ( null === $entity['starts_at'] ) {
				continue;
			}

			++$scheduled;

			$this->assertGreaterThan( $now, strtotime( (string) $entity['starts_at'] ) );
			// An expiry before the start is a coupon that is never valid, which is a fixture
			// nobody wants and every platform accepts.
			$this->assertGreaterThan(
				strtotime( (string) $entity['starts_at'] ),
				strtotime( (string) $entity['expires_at'] )
			);
		}

		$this->assertGreaterThan( 0, $scheduled );
		$this->assertLessThan( 60, $scheduled );
	}

	public function test_every_coupon_expires_in_the_future(): void {
		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertGreaterThan( time(), strtotime( (string) $this->entity()['expires_at'] ) );
		}
	}

	// -----------------------------------------------------------------------------------
	// The parameters that used to be ignored.
	// -----------------------------------------------------------------------------------

	/**
	 * The headline bug: `discount_types` was declared on three surfaces and read on none, so asking
	 * for percentage coupons got a spread across all three types.
	 */
	public function test_the_requested_type_is_the_only_one_produced(): void {
		$this->with( array( 'discount_types' => array( 'fixed' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertSame( 'fixed', $this->entity()['type'] );
		}
	}

	/**
	 * `fixed_amount` was the endpoint's spelling and no platform's. It is dropped rather than
	 * matched loosely, and dropping every entry falls back to the full spread.
	 */
	public function test_an_unknown_type_falls_back_to_the_spread(): void {
		$this->with( array( 'discount_types' => array( 'fixed_amount', 'buy_x_get_y', 'products' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertContains( $this->entity()['type'], array( 'percentage', 'fixed', 'free_shipping' ) );
		}
	}

	public function test_a_known_type_survives_an_unknown_one_beside_it(): void {
		$this->with( array( 'discount_types' => array( 'fixed_amount', 'percentage' ) ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertSame( 'percentage', $this->entity()['type'] );
		}
	}

	public function test_the_percentage_range_is_honoured(): void {
		$this->with(
			array(
				'discount_types' => array( 'percentage' ),
				'discount_range' => array(
					'min_percentage' => 60,
					'max_percentage' => 65,
				),
			)
		);

		foreach ( range( 1, 25 ) as $ignored ) {
			$discount = $this->entity()['discount'];

			$this->assertGreaterThanOrEqual( 60, $discount );
			$this->assertLessThanOrEqual( 65, $discount );
		}
	}

	/**
	 * A maximum below the minimum is a request nobody meant, and FakerPHP throws on it rather than
	 * choosing.
	 */
	public function test_an_inverted_range_does_not_fail(): void {
		$this->with(
			array(
				'discount_types' => array( 'percentage' ),
				'discount_range' => array(
					'min_percentage' => 80,
					'max_percentage' => 20,
				),
			)
		);

		foreach ( range( 1, 10 ) as $ignored ) {
			$discount = $this->entity()['discount'];

			$this->assertGreaterThanOrEqual( 20, $discount );
			$this->assertLessThanOrEqual( 80, $discount );
		}
	}

	public function test_the_validity_period_is_honoured(): void {
		$this->with(
			array(
				'validity_period' => array(
					'min_days' => 2,
					'max_days' => 3,
				),
			)
		);

		foreach ( range( 1, 25 ) as $ignored ) {
			$entity = $this->entity();
			$from   = null === $entity['starts_at'] ? time() : strtotime( (string) $entity['starts_at'] );
			$days   = (int) round( ( strtotime( (string) $entity['expires_at'] ) - $from ) / DAY_IN_SECONDS );

			$this->assertGreaterThanOrEqual( 2, $days );
			$this->assertLessThanOrEqual( 3, $days );
		}
	}

	public function test_the_usage_limits_are_honoured(): void {
		$this->with( array( 'usage_limits' => array( 'max_uses' => 20 ) ) );

		foreach ( range( 1, 25 ) as $ignored ) {
			$uses = $this->entity()['usage_limit'];

			$this->assertGreaterThanOrEqual( 1, $uses );
			$this->assertLessThanOrEqual( 20, $uses );
		}
	}

	public function test_the_restriction_switches_are_honoured(): void {
		$this->with(
			array(
				'restrictions' => array(
					'minimum_spend'        => false,
					'maximum_spend'        => true,
					'exclude_sale_items'   => true,
					'product_restrictions' => false,
				),
			)
		);

		foreach ( range( 1, 15 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertNull( $entity['minimum_amount'] );
			$this->assertIsInt( $entity['maximum_amount'] );
			$this->assertTrue( $entity['exclude_sale_items'] );
			$this->assertSame( 0, $entity['product_count'] );
		}
	}

	/**
	 * Cart thresholds are minor units like every other amount, and null means no threshold at all
	 * rather than a threshold of zero — which reads as "any cart" until somebody sorts the column.
	 */
	public function test_the_thresholds_are_minor_units(): void {
		$this->with(
			array(
				'restrictions' => array(
					'minimum_spend' => true,
					'maximum_spend' => true,
				),
			)
		);

		foreach ( range( 1, 15 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertIsInt( $entity['minimum_amount'] );
			$this->assertIsInt( $entity['maximum_amount'] );
			$this->assertSame( 0, $entity['minimum_amount'] % 100 );
			$this->assertGreaterThan( $entity['minimum_amount'], $entity['maximum_amount'] );
		}
	}

	/**
	 * A store where every coupon stacks cannot test the coupon that refuses to.
	 */
	public function test_both_stacking_outcomes_occur(): void {
		$seen = array();

		foreach ( range( 1, 60 ) as $ignored ) {
			$seen[ $this->entity()['stackable'] ? 'yes' : 'no' ] = true;
		}

		$this->assertCount( 2, $seen );
	}

	/**
	 * The generator still names no platform, which the added fields are the most likely thing to
	 * break: `individual_use` is WooCommerce's inverse of stacking, and `min_purchase_amount` and
	 * `max_per_customer` are Fluent Cart's names for two fields declared here.
	 */
	public function test_the_entity_names_no_platform(): void {
		$serialised = (string) wp_json_encode( $this->entity() );

		foreach (
			array( 'individual_use', 'min_purchase_amount', 'max_per_customer', 'fixed_cart', 'conditions' ) as $word
		) {
			$this->assertStringNotContainsString( $word, $serialised, $word );
		}
	}
}
