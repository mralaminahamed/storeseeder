<?php
/**
 * Tests for the product properties, and for the parameters that used to do nothing.
 *
 * The Products generator declared six parameter groups and read exactly one. `price_range` was the
 * worst of them: the admin offered a minimum and a maximum, and every product came out between
 * 9.99 and 999.99 regardless. These tests exist so that cannot come back — a parameter that is
 * declared has to change the output, or it should not be declared.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generators;

use StoreSeeder\Generation\Generators\Product;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generators\Product
 */
class ProductPropertiesTest extends StoreSeederUnitTestCase {

	/**
	 * Entity assertions need neither the REST server nor a database fixture.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	/**
	 * The generator under test.
	 *
	 * @var Product
	 */
	private Product $generator;

	public function setUp(): void {
		parent::setUp();

		$this->generator = new Product();
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
				'title',
				'slug',
				'description',
				'short_description',
				'price',
				'sale_price',
				'cost',
				'status',
				'sku',
				'stock',
				'manage_stock',
				'backorders',
				'sold_individually',
				'fulfillment_type',
				'category_count',
			) as $field
		) {
			$this->assertArrayHasKey( $field, $entity, $field );
		}
	}

	public function test_the_slug_follows_the_title(): void {
		$entity = $this->entity();

		$this->assertSame( sanitize_title( (string) $entity['title'] ), $entity['slug'] );
	}

	/**
	 * Money stays an integer in the currency's minor unit, which is the rule a second price is most
	 * likely to break — 45.67 and 4567 both look plausible in a column.
	 */
	public function test_every_money_field_is_minor_units(): void {
		$this->with( array( 'track_cost' => true ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertIsInt( $entity['price'] );

			foreach ( array( 'sale_price', 'cost' ) as $field ) {
				if ( null !== $entity[ $field ] ) {
					$this->assertIsInt( $entity[ $field ], $field );
				}
			}
		}
	}

	public function test_a_sale_price_is_below_the_regular_price(): void {
		$found = false;

		// Thirty percent of products are discounted, so a run of forty finds one with certainty
		// enough for a test that also tolerates none.
		foreach ( range( 1, 40 ) as $ignored ) {
			$entity = $this->entity();

			if ( null === $entity['sale_price'] ) {
				continue;
			}

			$found = true;
			$this->assertLessThan( $entity['price'], $entity['sale_price'] );
			$this->assertGreaterThan( 0, $entity['sale_price'] );
		}

		$this->assertTrue( $found, 'No product in forty carried a sale price.' );
	}

	/**
	 * Cost is off unless asked for: a store that does not track cost of goods is the common case,
	 * and inventing a margin for every product would be data nobody asked for.
	 */
	public function test_cost_is_absent_until_requested(): void {
		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertNull( $this->entity()['cost'] );
		}

		$this->with( array( 'track_cost' => true ) );

		$entity = $this->entity();

		$this->assertIsInt( $entity['cost'] );
		$this->assertLessThan( $entity['price'], $entity['cost'] );
	}

	// -----------------------------------------------------------------------------------
	// The parameters that used to be ignored.
	// -----------------------------------------------------------------------------------

	/**
	 * The headline bug: a declared price range that nothing read.
	 */
	public function test_the_price_range_is_honoured(): void {
		$this->with(
			array(
				'price_range' => array(
					'min' => 20,
					'max' => 25,
				),
			)
		);

		foreach ( range( 1, 25 ) as $ignored ) {
			$price = $this->entity()['price'];

			$this->assertGreaterThanOrEqual( 2000, $price );
			$this->assertLessThanOrEqual( 2500, $price );
		}
	}

	/**
	 * A maximum below the minimum is a request nobody meant, and FakerPHP throws on it rather than
	 * choosing — so the wider of the two wins instead of the run failing.
	 */
	public function test_an_inverted_price_range_does_not_fail(): void {
		$this->with(
			array(
				'price_range' => array(
					'min' => 90,
					'max' => 10,
				),
			)
		);

		$this->assertSame( 9000, $this->entity()['price'] );
	}

	public function test_the_stock_range_is_honoured(): void {
		$this->with(
			array(
				'inventory' => array(
					'manage_stock' => true,
					'stock_range'  => array(
						'min' => 7,
						'max' => 9,
					),
				),
			)
		);

		foreach ( range( 1, 25 ) as $ignored ) {
			$stock = $this->entity()['stock'];

			$this->assertGreaterThanOrEqual( 7, $stock );
			$this->assertLessThanOrEqual( 9, $stock );
		}
	}

	/**
	 * Unmanaged stock has no quantity at all, rather than a quantity of zero — which every platform
	 * would read as out of stock.
	 */
	public function test_stock_is_null_when_management_is_off(): void {
		$this->with( array( 'inventory' => array( 'manage_stock' => false ) ) );

		$entity = $this->entity();

		$this->assertFalse( $entity['manage_stock'] );
		$this->assertNull( $entity['stock'] );
	}

	public function test_the_description_length_is_honoured(): void {
		$lengths = array();

		foreach ( array( 'short', 'medium', 'long' ) as $length ) {
			$this->with( array( 'content_options' => array( 'description_length' => $length ) ) );

			$lengths[ $length ] = substr_count( trim( (string) $this->entity()['description'] ), "\n\n" ) + 1;
		}

		$this->assertSame( 1, $lengths['short'] );
		$this->assertSame( 3, $lengths['medium'] );
		$this->assertSame( 6, $lengths['long'] );
	}

	public function test_categories_per_product_respects_the_maximum(): void {
		$this->with( array( 'categories' => array( 'max_per_product' => 2 ) ) );

		foreach ( range( 1, 25 ) as $ignored ) {
			$count = $this->entity()['category_count'];

			$this->assertGreaterThanOrEqual( 0, $count );
			$this->assertLessThanOrEqual( 2, $count );
		}
	}

	public function test_zero_categories_is_a_real_choice(): void {
		$this->with( array( 'categories' => array( 'max_per_product' => 0 ) ) );

		$this->assertSame( 0, $this->entity()['category_count'] );
	}

	/**
	 * Backorders use the canonical three values. A platform whose column holds two says so through
	 * its capability rather than flattening them here, so the entity keeps all three.
	 */
	public function test_backorders_use_the_canonical_vocabulary(): void {
		$seen = array();

		foreach ( range( 1, 60 ) as $ignored ) {
			$seen[ (string) $this->entity()['backorders'] ] = true;
		}

		foreach ( array_keys( $seen ) as $value ) {
			$this->assertContains( $value, array( 'no', 'notify', 'yes' ) );
		}

		// All three occur, or the fixture is not exercising the case the mapping exists for.
		$this->assertCount( 3, $seen );
	}

	/**
	 * The generator still names no platform, which the added fields are the most likely thing to
	 * break — `compare_price` and `cogs_value` are each one platform's word for a shared idea.
	 */
	public function test_the_entity_names_no_platform(): void {
		$this->with( array( 'track_cost' => true ) );

		$serialised = (string) wp_json_encode( $this->entity() );

		foreach ( array( 'compare_price', 'cogs', 'item_cost', 'regular_price', 'product_cat' ) as $word ) {
			$this->assertStringNotContainsString( $word, $serialised, $word );
		}
	}
}
