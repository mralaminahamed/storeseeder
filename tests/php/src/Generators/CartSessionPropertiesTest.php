<?php
/**
 * Tests for the cart session properties, and for the parameters that used to do nothing.
 *
 * Seven of the endpoint's eight parameters were read by nothing. A run asking for a store full of
 * abandoned carts got an even third of each stage, the guest share was fixed at 30% whatever was
 * asked, and every cart carried one to five items regardless. The generator also emitted Fluent
 * Cart's own words for the stages — `draft`, `intended`, `completed` — which is a platform name in a
 * canonical entity.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generators;

use StoreSeeder\Generation\Generators\Cart_Session;
use StoreSeeder\Platforms\Status;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generators\Cart_Session
 * @covers \StoreSeeder\Platforms\Status::cart_stages
 */
class CartSessionPropertiesTest extends StoreSeederUnitTestCase {

	/**
	 * Entity assertions need neither the REST server nor a database fixture.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	/**
	 * The generator under test.
	 *
	 * @var Cart_Session
	 */
	private Cart_Session $generator;

	public function setUp(): void {
		parent::setUp();

		$this->generator = new Cart_Session();
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
	// The canonical vocabulary.
	// -----------------------------------------------------------------------------------

	public function test_the_entity_carries_the_unified_fields(): void {
		$entity = $this->entity();

		foreach ( array( 'stage', 'logged_in', 'items', 'user_agent', 'ip_address', 'created_at' ) as $field ) {
			$this->assertArrayHasKey( $field, $entity, $field );
		}
	}

	/**
	 * The stages used to be Fluent Cart's own words in a canonical entity, which is the one thing a
	 * generator may not do.
	 */
	public function test_every_stage_is_canonical(): void {
		foreach ( range( 1, 60 ) as $ignored ) {
			$this->assertContains( $this->entity()['stage'], Status::cart_stages() );
		}
	}

	public function test_the_line_prices_are_minor_units(): void {
		foreach ( range( 1, 15 ) as $ignored ) {
			foreach ( $this->entity()['items'] as $item ) {
				$this->assertIsInt( $item['unit_price'] );
				$this->assertGreaterThan( 0, $item['quantity'] );
			}
		}
	}

	/**
	 * A cart that reached checkout has checkout data — the abandoned ones and the converted ones,
	 * since a cart cannot convert without passing through it. A still-active cart has none, and
	 * inventing an address for one makes every checkout funnel read the same number twice.
	 */
	public function test_checkout_data_belongs_to_a_cart_that_reached_checkout(): void {
		$this->with( array( 'status_distribution' => array( 'pending' => 100 ) ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertSame( Status::CART_ACTIVE, $entity['stage'] );
			$this->assertArrayNotHasKey( 'checkout', $entity );
		}

		foreach ( array( 'abandoned', 'completed' ) as $key ) {
			$this->with( array( 'status_distribution' => array( $key => 100 ) ) );

			foreach ( range( 1, 10 ) as $ignored ) {
				$this->assertArrayHasKey( 'checkout', $this->entity(), $key );
			}
		}
	}

	/**
	 * Left to the platform, every abandoned cart in the store was abandoned today — and the age of
	 * the cart is the whole input to a recovery report.
	 */
	public function test_the_cart_carries_its_own_start_date(): void {
		foreach ( range( 1, 20 ) as $ignored ) {
			$created = strtotime( (string) $this->entity()['created_at'] );

			$this->assertLessThanOrEqual( time(), $created );
			$this->assertGreaterThan( time() - 31 * DAY_IN_SECONDS, $created );
		}
	}

	// -----------------------------------------------------------------------------------
	// The parameters that used to be ignored.
	// -----------------------------------------------------------------------------------

	public function test_the_abandonment_rate_is_honoured(): void {
		$this->with( array( 'abandonment_rate' => 100 ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertSame( Status::CART_ABANDONED, $this->entity()['stage'] );
		}

		$this->with( array( 'abandonment_rate' => 0 ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertNotSame( Status::CART_ABANDONED, $this->entity()['stage'] );
		}
	}

	/**
	 * The remainder splits between a cart still being filled and one that converted, so switching
	 * abandonment off still produces both.
	 */
	public function test_no_abandonment_still_produces_both_other_stages(): void {
		$this->with( array( 'abandonment_rate' => 0 ) );

		$seen = array();

		foreach ( range( 1, 60 ) as $ignored ) {
			$seen[ $this->entity()['stage'] ] = true;
		}

		$this->assertSame(
			array( Status::CART_ACTIVE, Status::CART_CONVERTED ),
			array_values( array_intersect( Status::cart_stages(), array_keys( $seen ) ) )
		);
	}

	public function test_the_stage_weights_win_over_the_rate(): void {
		$this->with(
			array(
				'abandonment_rate'    => 100,
				'status_distribution' => array( 'completed' => 100 ),
			)
		);

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertSame( Status::CART_CONVERTED, $this->entity()['stage'] );
		}
	}

	/**
	 * `pending` and `completed` are the names the schema shipped with; the canonical stages are
	 * accepted too, so a caller who read the vocabulary can use it.
	 */
	public function test_the_weights_accept_the_canonical_stage_names(): void {
		$this->with( array( 'status_distribution' => array( Status::CART_CONVERTED => 100 ) ) );

		foreach ( range( 1, 15 ) as $ignored ) {
			$this->assertSame( Status::CART_CONVERTED, $this->entity()['stage'] );
		}
	}

	/**
	 * Weights that are all zero would divide by nothing. An abandoned cart is the answer, since a
	 * caller who set every weight to zero asked for the thing this resource exists to generate.
	 */
	public function test_empty_weights_do_not_divide_by_zero(): void {
		$this->with( array( 'status_distribution' => array( 'pending' => 0 ) ) );

		$this->assertContains( $this->entity()['stage'], Status::cart_stages() );
	}

	public function test_the_guest_ratio_is_honoured(): void {
		$this->with( array( 'guest_cart_ratio' => 100 ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertFalse( $entity['logged_in'] );
			// A guest cart carries its own contact details, because there is no account to read
			// them from.
			$this->assertArrayHasKey( 'guest', $entity );
			$this->assertNotEmpty( $entity['guest']['email'] );
		}

		$this->with( array( 'guest_cart_ratio' => 0 ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertTrue( $entity['logged_in'] );
			$this->assertArrayNotHasKey( 'guest', $entity );
		}
	}

	/**
	 * `customer_type: guest_only` is the older way of asking for a ratio of 100, and still works —
	 * it shipped on three surfaces.
	 */
	public function test_guest_only_is_accepted_as_a_ratio_of_a_hundred(): void {
		$this->with( array( 'customer_type' => 'guest_only' ) );

		foreach ( range( 1, 15 ) as $ignored ) {
			$this->assertFalse( $this->entity()['logged_in'] );
		}
	}

	public function test_the_item_range_is_honoured(): void {
		$this->with(
			array(
				'items_per_cart' => array(
					'min' => 6,
					'max' => 8,
				),
			)
		);

		foreach ( range( 1, 25 ) as $ignored ) {
			$count = count( $this->entity()['items'] );

			$this->assertGreaterThanOrEqual( 6, $count );
			$this->assertLessThanOrEqual( 8, $count );
		}
	}

	public function test_an_inverted_item_range_does_not_fail(): void {
		$this->with(
			array(
				'items_per_cart' => array(
					'min' => 5,
					'max' => 2,
				),
			)
		);

		$this->assertCount( 5, $this->entity()['items'] );
	}

	/**
	 * The generator still names no platform. The stages are the likeliest thing to break it, since
	 * they were Fluent Cart's own words until now.
	 */
	public function test_the_entity_names_no_platform(): void {
		foreach ( range( 1, 20 ) as $ignored ) {
			$serialised = (string) wp_json_encode( $this->entity() );

			foreach ( array( 'draft', 'intended', 'cart_group', 'cart_hash', 'fct_' ) as $word ) {
				$this->assertStringNotContainsString( $word, $serialised, $word );
			}
		}
	}
}
