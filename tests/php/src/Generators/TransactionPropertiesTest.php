<?php
/**
 * Tests for the transaction properties, and for the parameters that used to do nothing.
 *
 * Six of the endpoint's seven parameters were read by nothing, and the admin and the MCP ability
 * declared a different set again — `payment_gateways` for `payment_methods`, and a `transaction_types`
 * enum of `payment`, `adjustment`, `fee` and `commission`, none of which is a type any platform
 * stores. A third of every run came out as refunds however few were asked for.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generators;

use StoreSeeder\Generation\Generators\Transaction;
use StoreSeeder\Platforms\Status;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generators\Transaction
 * @covers \StoreSeeder\Platforms\Status::transaction_statuses
 * @covers \StoreSeeder\Platforms\Status::transaction_types
 */
class TransactionPropertiesTest extends StoreSeederUnitTestCase {

	/**
	 * Entity assertions need neither the REST server nor a database fixture.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	/**
	 * The generator under test.
	 *
	 * @var Transaction
	 */
	private Transaction $generator;

	public function setUp(): void {
		parent::setUp();

		$this->generator = new Transaction();
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

		foreach (
			array(
				'vendor_charge_id',
				'payment_method',
				'payment_mode',
				'currency',
				'transaction_type',
				'status',
				'total',
				'rate',
				'payer_email',
				'created_at',
				'with_metadata',
			) as $field
		) {
			$this->assertArrayHasKey( $field, $entity, $field );
		}
	}

	public function test_every_status_is_canonical(): void {
		foreach ( range( 1, 60 ) as $ignored ) {
			$this->assertContains( $this->entity()['status'], Status::transaction_statuses() );
		}
	}

	public function test_every_type_is_canonical(): void {
		$this->with( array( 'transaction_types' => Status::transaction_types() ) );

		foreach ( range( 1, 60 ) as $ignored ) {
			$this->assertContains( $this->entity()['transaction_type'], Status::transaction_types() );
		}
	}

	/**
	 * The type decides the status for the two that have only one. A refund transaction marked pending
	 * is something no gateway produces and no report can read.
	 */
	public function test_a_refund_is_refunded_and_a_dispute_is_disputed(): void {
		$this->with(
			array(
				'transaction_types'    => array( Status::REFUND ),
				// Deliberately contradicted: the type wins.
				'transaction_statuses' => array( Status::PENDING ),
				'refund_percentage'    => 100,
			)
		);

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertSame( Status::REFUNDED, $this->entity()['status'] );
		}

		$this->with( array( 'transaction_types' => array( Status::DISPUTE ) ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertSame( Status::DISPUTED, $this->entity()['status'] );
		}
	}

	public function test_the_total_is_minor_units(): void {
		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertIsInt( $this->entity()['total'] );
		}
	}

	// -----------------------------------------------------------------------------------
	// The parameters that used to be ignored.
	// -----------------------------------------------------------------------------------

	public function test_the_amount_range_is_honoured(): void {
		$this->with(
			array(
				'amount_range' => array(
					'min' => 40,
					'max' => 42,
				),
			)
		);

		foreach ( range( 1, 25 ) as $ignored ) {
			$total = $this->entity()['total'];

			$this->assertGreaterThanOrEqual( 4000, $total );
			$this->assertLessThanOrEqual( 4200, $total );
		}
	}

	public function test_the_payment_methods_are_honoured(): void {
		$this->with( array( 'payment_methods' => array( 'bank_transfer' ) ) );

		foreach ( range( 1, 15 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertSame( 'bank_transfer', $entity['payment_method'] );
			// A bank transfer has no card behind it, whatever its status.
			$this->assertArrayNotHasKey( 'card_brand', $entity );
		}
	}

	/**
	 * The admin and the MCP ability called this `payment_gateways`, the endpoint `payment_methods`.
	 * Both work, because both shipped.
	 */
	public function test_the_gateway_synonym_is_accepted(): void {
		$this->with( array( 'payment_gateways' => array( 'paypal' ) ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertSame( 'paypal', $this->entity()['payment_method'] );
		}
	}

	public function test_the_charge_statuses_are_honoured(): void {
		$this->with(
			array(
				'transaction_types'    => array( Status::CHARGE ),
				'transaction_statuses' => array( Status::AUTHORIZED ),
			)
		);

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertSame( Status::AUTHORIZED, $this->entity()['status'] );
		}
	}

	/**
	 * `refunded` and `disputed` belong to their own types, so a charge is never given either: a charge
	 * marked refunded contradicts the refund transaction beside it.
	 */
	public function test_a_charge_is_never_refunded_or_disputed(): void {
		$this->with(
			array(
				'transaction_types'    => array( Status::CHARGE ),
				'transaction_statuses' => array( Status::REFUNDED, Status::DISPUTED ),
			)
		);

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertContains(
				$this->entity()['status'],
				array( Status::COMPLETED, Status::PENDING, Status::FAILED )
			);
		}
	}

	public function test_the_refund_share_is_honoured(): void {
		$this->with(
			array(
				'transaction_types' => array( Status::CHARGE, Status::REFUND ),
				'refund_percentage' => 100,
			)
		);

		foreach ( range( 1, 15 ) as $ignored ) {
			$this->assertSame( Status::REFUND, $this->entity()['transaction_type'] );
		}

		$this->with(
			array(
				'transaction_types' => array( Status::CHARGE, Status::REFUND ),
				'refund_percentage' => 0,
			)
		);

		foreach ( range( 1, 15 ) as $ignored ) {
			$this->assertNotSame( Status::REFUND, $this->entity()['transaction_type'] );
		}
	}

	/**
	 * `include_refunds` is the endpoint's older way of leaving `refund` out of the list, and still
	 * works — it shipped, so a caller sending it must not start getting refunds.
	 */
	public function test_refunds_can_be_switched_off_by_the_older_name(): void {
		$this->with(
			array(
				'include_refunds'   => false,
				'refund_percentage' => 100,
			)
		);

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertNotSame( Status::REFUND, $this->entity()['transaction_type'] );
		}
	}

	public function test_asking_only_for_refunds_gives_only_refunds(): void {
		$this->with( array( 'transaction_types' => array( Status::REFUND ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertSame( Status::REFUND, $this->entity()['transaction_type'] );
		}
	}

	public function test_an_unknown_type_falls_back_to_charges_and_refunds(): void {
		$this->with( array( 'transaction_types' => array( 'payment', 'adjustment', 'fee', 'commission' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertContains(
				$this->entity()['transaction_type'],
				array( Status::CHARGE, Status::REFUND )
			);
		}
	}

	public function test_gateway_metadata_can_be_switched_off(): void {
		$this->with(
			array(
				'include_gateway_metadata' => false,
				'payment_methods'          => array( 'stripe' ),
				'transaction_types'        => array( Status::CHARGE ),
				'transaction_statuses'     => array( Status::COMPLETED ),
			)
		);

		foreach ( range( 1, 15 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertFalse( $entity['with_metadata'] );
			$this->assertSame( '', $entity['payer_email'] );
			$this->assertArrayNotHasKey( 'card_last_4', $entity );
		}
	}

	/**
	 * Card details belong to a card payment that went through — an authorized or failed charge has no
	 * settled card behind it in the data every gateway returns.
	 */
	public function test_card_details_accompany_a_completed_card_payment(): void {
		$this->with(
			array(
				'payment_methods'      => array( 'stripe' ),
				'transaction_types'    => array( Status::CHARGE ),
				'transaction_statuses' => array( Status::COMPLETED ),
			)
		);

		foreach ( range( 1, 10 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertArrayHasKey( 'card_last_4', $entity );
			$this->assertArrayHasKey( 'card_brand', $entity );
			$this->assertGreaterThanOrEqual( 1000, $entity['card_last_4'] );
		}

		$this->with(
			array(
				'payment_methods'      => array( 'stripe' ),
				'transaction_types'    => array( Status::CHARGE ),
				'transaction_statuses' => array( Status::FAILED ),
			)
		);

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertArrayNotHasKey( 'card_last_4', $this->entity() );
		}
	}

	/**
	 * Left to the platform, every transaction on a two-year-old order was stamped today, which makes
	 * a settlement report meaningless. The writer still clamps this forward to its parent order,
	 * since only the writer knows the parent.
	 */
	public function test_the_transaction_carries_its_own_date(): void {
		foreach ( range( 1, 20 ) as $ignored ) {
			$created = strtotime( (string) $this->entity()['created_at'] );

			$this->assertLessThanOrEqual( time(), $created );
			$this->assertGreaterThan( time() - 91 * DAY_IN_SECONDS, $created );
		}
	}

	/**
	 * The generator still names no platform. Fluent Cart spells completed `succeeded` and disputed
	 * `dispute_lost`, and both belong in its writer.
	 */
	public function test_the_entity_names_no_platform(): void {
		$this->with( array( 'transaction_types' => Status::transaction_types() ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$serialised = (string) wp_json_encode( $this->entity() );

			foreach ( array( 'succeeded', 'dispute_lost', 'order_type', 'fct_' ) as $word ) {
				$this->assertStringNotContainsString( $word, $serialised, $word );
			}
		}
	}
}
