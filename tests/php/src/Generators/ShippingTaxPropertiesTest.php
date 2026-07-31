<?php
/**
 * Tests for the shipping-plan and tax-class properties.
 *
 * Between them these two declared nine parameters and read none. A shipping method was a coin toss
 * between a flat rate and free shipping whatever service was asked for; a tax class came out as two
 * random words at a random percentage, with a two-letter state code like "QK" that matches nothing at
 * checkout.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generators;

use StoreSeeder\Generation\Generators\Shipping_Plan;
use StoreSeeder\Generation\Generators\Tax_Class;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generators\Shipping_Plan
 * @covers \StoreSeeder\Generation\Generators\Tax_Class
 */
class ShippingTaxPropertiesTest extends StoreSeederUnitTestCase {

	/**
	 * Entity assertions need neither the REST server nor a database fixture.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	/**
	 * Build one entity from a generator.
	 *
	 * @param object               $generator The generator under test.
	 * @param array<string, mixed> $params    Generation parameters.
	 *
	 * @return array<string, mixed>
	 */
	private function entity( object $generator, array $params = array() ): array {
		$generator->set_generation_params( $params );

		$method = new \ReflectionMethod( $generator, 'build_entity' );

		return (array) $method->invoke( $generator );
	}

	/**
	 * A shipping-plan generator, ready to build.
	 *
	 * @return Shipping_Plan
	 */
	private function shipping(): Shipping_Plan {
		$generator = new Shipping_Plan();
		$generator->set_locale( 'en_US' );
		$generator->set_faker();

		return $generator;
	}

	/**
	 * A tax-class generator, ready to build.
	 *
	 * @return Tax_Class
	 */
	private function tax(): Tax_Class {
		$generator = new Tax_Class();
		$generator->set_locale( 'en_US' );
		$generator->set_faker();

		return $generator;
	}

	// -----------------------------------------------------------------------------------
	// Shipping plans.
	// -----------------------------------------------------------------------------------

	public function test_a_plan_carries_the_unified_fields(): void {
		$entity = $this->entity( $this->shipping() );

		foreach (
			array( 'title', 'type', 'amount', 'description', 'enabled', 'regions', 'delivery_min', 'delivery_max' ) as $field
		) {
			$this->assertArrayHasKey( $field, $entity, $field );
		}
	}

	/**
	 * A service level is a flat rate with a name and a delivery window, because neither platform has
	 * an "express" method type. Only free shipping is a type of its own.
	 */
	public function test_a_service_level_is_a_flat_rate_with_a_name(): void {
		$generator = $this->shipping();

		foreach ( array( 'standard', 'express', 'overnight' ) as $service ) {
			$entity = $this->entity( $generator, array( 'shipping_types' => array( $service ) ) );

			$this->assertSame( 'flat_rate', $entity['type'], $service );
			$this->assertStringContainsStringIgnoringCase( $service, $entity['title'], $service );
		}

		$entity = $this->entity( $generator, array( 'shipping_types' => array( 'free' ) ) );

		$this->assertSame( 'free_shipping', $entity['type'] );
		$this->assertSame( 0, $entity['amount'] );
	}

	/**
	 * `flat_rate` was in the enum beside the services, and a flat rate with no service level is the
	 * standard one.
	 */
	public function test_flat_rate_is_accepted_as_the_standard_service(): void {
		$entity = $this->entity( $this->shipping(), array( 'shipping_types' => array( 'flat_rate' ) ) );

		$this->assertSame( 'flat_rate', $entity['type'] );
		$this->assertStringContainsString( 'Standard', $entity['title'] );
	}

	public function test_the_cost_range_is_honoured(): void {
		$generator = $this->shipping();

		foreach ( range( 1, 20 ) as $ignored ) {
			$entity = $this->entity(
				$generator,
				array(
					'shipping_types' => array( 'standard' ),
					'cost_range'     => array(
						'min' => 20,
						'max' => 22,
					),
				)
			);

			$this->assertGreaterThanOrEqual( 2000, $entity['amount'] );
			$this->assertLessThanOrEqual( 2200, $entity['amount'] );
		}
	}

	/**
	 * An overnight method that quotes nine days is not a fixture anybody can read, so the service
	 * narrows whatever window was allowed.
	 */
	public function test_the_service_narrows_the_delivery_window(): void {
		$generator = $this->shipping();
		$frames    = array(
			'min_days' => 1,
			'max_days' => 10,
		);

		foreach ( range( 1, 10 ) as $ignored ) {
			$overnight = $this->entity(
				$generator,
				array(
					'shipping_types'      => array( 'overnight' ),
					'delivery_timeframes' => $frames,
				)
			);

			$this->assertLessThanOrEqual( 1, $overnight['delivery_max'] );

			$express = $this->entity(
				$generator,
				array(
					'shipping_types'      => array( 'express' ),
					'delivery_timeframes' => $frames,
				)
			);

			// The fast half of the range.
			$this->assertLessThanOrEqual( 5, $express['delivery_max'] );
		}
	}

	public function test_the_delivery_window_never_runs_backwards(): void {
		$generator = $this->shipping();

		foreach ( range( 1, 30 ) as $ignored ) {
			$entity = $this->entity( $generator );

			$this->assertLessThanOrEqual( $entity['delivery_max'], $entity['delivery_min'] );
			$this->assertStringContainsString( (string) $entity['delivery_max'], $entity['title'] );
		}
	}

	/**
	 * A worldwide plan is the empty list, because that is how both platforms spell "no restriction".
	 * Domestic is a placeholder the writer resolves, since only it knows where the store sells from.
	 */
	public function test_the_coverage_areas_are_honoured(): void {
		$generator = $this->shipping();

		$this->assertSame(
			array(),
			$this->entity( $generator, array( 'coverage_areas' => array( 'worldwide' ) ) )['regions']
		);

		$this->assertSame(
			array( 'store_country' ),
			$this->entity( $generator, array( 'coverage_areas' => array( 'domestic' ) ) )['regions']
		);

		$regional = $this->entity( $generator, array( 'coverage_areas' => array( 'regional' ) ) )['regions'];

		$this->assertCount( 3, $regional );

		foreach ( $regional as $code ) {
			$this->assertMatchesRegularExpression( '/^[A-Z]{2}$/', $code );
		}
	}

	// -----------------------------------------------------------------------------------
	// Tax classes.
	// -----------------------------------------------------------------------------------

	public function test_a_tax_class_carries_the_unified_fields(): void {
		$entity = $this->entity( $this->tax() );

		foreach ( array( 'name', 'type', 'rate', 'country', 'state', 'status', 'rates' ) as $field ) {
			$this->assertArrayHasKey( $field, $entity, $field );
		}

		foreach ( $entity['rates'] as $row ) {
			foreach ( array( 'country', 'state', 'city', 'postcode', 'rate', 'compound', 'priority' ) as $field ) {
				$this->assertArrayHasKey( $field, $row, $field );
			}
		}
	}

	/**
	 * "Corrupti Quia Tax" tells a reader nothing about whether it is the reduced rate or the zero one.
	 */
	public function test_a_class_is_named_for_what_it_is(): void {
		$generator = $this->tax();
		$names     = array(
			'standard' => 'Standard Rate',
			'reduced'  => 'Reduced Rate',
			'zero'     => 'Zero Rate',
			'exempt'   => 'Tax Exempt',
			'digital'  => 'Digital Goods',
		);

		foreach ( $names as $type => $expected ) {
			$entity = $this->entity( $generator, array( 'tax_types' => array( $type ) ) );

			$this->assertSame( $type, $entity['type'] );
			$this->assertStringStartsWith( $expected, $entity['name'] );
		}
	}

	/**
	 * A zero-rated class is zero-rated and an exempt one is exempt. Drawing a percentage for either
	 * produces a class whose name contradicts its rate.
	 */
	public function test_a_zero_rated_class_is_zero_whatever_range_is_given(): void {
		$generator = $this->tax();

		foreach ( array( 'zero', 'exempt' ) as $type ) {
			$entity = $this->entity(
				$generator,
				array(
					'tax_types'   => array( $type ),
					'rate_ranges' => array( $type => array( 'min' => 20, 'max' => 25 ) ),
				)
			);

			$this->assertSame( 0.0, $entity['rate'], $type );

			foreach ( $entity['rates'] as $row ) {
				$this->assertSame( 0.0, $row['rate'] );
			}
		}
	}

	public function test_the_rate_band_is_honoured_per_type(): void {
		$generator = $this->tax();

		foreach ( range( 1, 20 ) as $ignored ) {
			$entity = $this->entity(
				$generator,
				array(
					'tax_types'   => array( 'reduced' ),
					'rate_ranges' => array( 'reduced' => array( 'min' => 6, 'max' => 7 ) ),
				)
			);

			$this->assertGreaterThanOrEqual( 6, $entity['rate'] );
			$this->assertLessThanOrEqual( 7, $entity['rate'] );
		}
	}

	public function test_the_jurisdictions_decide_how_precise_a_rate_is(): void {
		$generator = $this->tax();

		$country = $this->entity( $generator, array( 'jurisdictions' => array( 'country' ) ) );

		foreach ( $country['rates'] as $row ) {
			$this->assertSame( '', $row['state'] );
			$this->assertSame( '', $row['city'] );
			$this->assertSame( '', $row['postcode'] );
			// Nothing below the country level, so nothing to outrank.
			$this->assertSame( 1, $row['priority'] );
		}

		$precise = $this->entity(
			$generator,
			array(
				'jurisdictions'     => array( 'country', 'state', 'city', 'postcode' ),
				'location_coverage' => array( 'countries' => array( 'US' ) ),
			)
		);

		foreach ( $precise['rates'] as $row ) {
			$this->assertNotSame( '', $row['city'] );
			$this->assertNotSame( '', $row['postcode'] );
			// A more precise rate has to outrank a broader one.
			$this->assertSame( 4, $row['priority'] );
		}
	}

	public function test_the_countries_are_honoured(): void {
		$generator = $this->tax();

		foreach ( range( 1, 15 ) as $ignored ) {
			$entity = $this->entity(
				$generator,
				array( 'location_coverage' => array( 'countries' => array( 'DE', 'FR' ) ) )
			);

			$this->assertContains( $entity['country'], array( 'DE', 'FR' ) );

			foreach ( $entity['rates'] as $row ) {
				$this->assertContains( $row['country'], array( 'DE', 'FR' ) );
			}
		}
	}

	/**
	 * Two random letters is what a state used to be, which produces codes like "QK" that match nothing
	 * at checkout. Only a country with real abbreviations gets one.
	 */
	public function test_a_state_is_real_or_absent(): void {
		$generator = $this->tax();
		$states    = array();

		foreach ( range( 1, 20 ) as $ignored ) {
			$entity = $this->entity(
				$generator,
				array( 'location_coverage' => array( 'countries' => array( 'US' ) ) )
			);

			$states[ $entity['state'] ] = true;
		}

		foreach ( array_keys( $states ) as $state ) {
			$this->assertMatchesRegularExpression( '/^[A-Z]{2}$/', $state );
		}

		$entity = $this->entity(
			$generator,
			array( 'location_coverage' => array( 'countries' => array( 'DE' ) ) )
		);

		$this->assertSame( '', $entity['state'] );
	}

	public function test_compound_rates_can_be_switched_off(): void {
		$generator = $this->tax();

		foreach ( range( 1, 20 ) as $ignored ) {
			$entity = $this->entity(
				$generator,
				array( 'location_coverage' => array( 'include_compound' => false ) )
			);

			foreach ( $entity['rates'] as $row ) {
				$this->assertFalse( $row['compound'] );
			}
		}
	}

	public function test_compound_rates_occur_when_allowed(): void {
		$generator = $this->tax();
		$compound  = 0;

		foreach ( range( 1, 60 ) as $ignored ) {
			foreach ( $this->entity( $generator )['rates'] as $row ) {
				if ( $row['compound'] ) {
					++$compound;
				}
			}
		}

		$this->assertGreaterThan( 0, $compound );
	}

	/**
	 * Neither generator names a platform. `fixed` is Fluent Cart's word for a flat rate and
	 * `tax_rate_class` is WooCommerce's column.
	 */
	public function test_neither_entity_names_a_platform(): void {
		$shipping = (string) wp_json_encode( $this->entity( $this->shipping() ) );
		$tax      = (string) wp_json_encode( $this->entity( $this->tax() ) );

		foreach ( array( 'fixed', 'is_enabled', 'zone_id' ) as $word ) {
			$this->assertStringNotContainsString( $word, $shipping, $word );
		}

		foreach ( array( 'tax_rate_class', 'is_compound', 'class_id', 'for_order' ) as $word ) {
			$this->assertStringNotContainsString( $word, $tax, $word );
		}
	}
}
