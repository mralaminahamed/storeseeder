<?php
/**
 * Test class for the Customer generator.
 *
 * @package FluentCartFakerPress\Tests
 */

namespace FluentCartFakerPress\Tests\Generators;

use FluentCartFakerPress\Generators\Customer;
use FluentCartFakerPress\Tests\FluentCartFakerPressUnitTestCase;

/**
 * Tests for the Customer generator.
 *
 * @covers \FluentCartFakerPress\Generators\Customer
 */
class CustomerGeneratorTest extends FluentCartFakerPressUnitTestCase {

	/**
	 * Generator tests need neither the REST server nor a fresh DB fixture.
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

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->generator = new Customer();
	}

	/**
	 * The generator instantiates.
	 *
	 * @return void
	 */
	public function test_generator_instantiation(): void {
		$this->assertInstanceOf( Customer::class, $this->generator );
	}

	/**
	 * The generator advertises its supported types and a description.
	 *
	 * @return void
	 */
	public function test_metadata(): void {
		$this->assertIsArray( $this->generator->get_supported_types() );
		$this->assertNotEmpty( $this->generator->get_supported_types() );
		$this->assertNotEmpty( $this->generator->get_description() );
	}

	/**
	 * Generating items returns an array of results, each keyed by id.
	 *
	 * @return void
	 */
	public function test_generate_returns_result_array(): void {
		$this->require_fluent_cart();

		$this->generator->set_locale( 'en_US' );
		$this->generator->set_faker();
		$this->generator->set_generation_params( array( 'count' => 3 ) );

		$result = $this->generator->generate( 3 );

		$this->assertIsArray( $result );
		$this->assertLessThanOrEqual( 3, count( $result ) );
		$this->assertIsArray( $this->generator->get_generation_errors() );

		foreach ( $result as $item ) {
			$this->assertIsArray( $item );
			$this->assertArrayHasKey( 'id', $item );
		}
	}

	/**
	 * Generating zero items yields an empty result set.
	 *
	 * @return void
	 */
	public function test_generate_zero_count_is_empty(): void {
		$this->require_fluent_cart();

		$this->generator->set_locale( 'en_US' );
		$this->generator->set_faker();
		$this->generator->set_generation_params( array( 'count' => 0 ) );

		$result = $this->generator->generate( 0 );

		$this->assertIsArray( $result );
		$this->assertCount( 0, $result );
	}
}
