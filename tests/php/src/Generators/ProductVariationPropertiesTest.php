<?php
/**
 * Tests for the product variation properties, and for the parameters that used to do nothing.
 *
 * Variations declared roughly fourteen distinct parameter names across three surfaces — the endpoint
 * had `variation_types` and `include_inventory`, the admin `price_variance` and `stock_settings`, the
 * MCP ability `stock_min` and `stock_max` — and read none of them. Every variation came out as a size
 * and a colour at a price unrelated to the product it hung off.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generators;

use StoreSeeder\Generation\Generators\Product_Variation;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generators\Product_Variation
 */
class ProductVariationPropertiesTest extends StoreSeederUnitTestCase {

	/**
	 * Entity assertions need neither the REST server nor a database fixture.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	/**
	 * The generator under test.
	 *
	 * @var Product_Variation
	 */
	private Product_Variation $generator;

	public function setUp(): void {
		parent::setUp();

		$this->generator = new Product_Variation();
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
			array( 'title', 'attributes', 'stock', 'manage_stock', 'sku', 'price', 'price_delta_percent', 'quantity' ) as $field
		) {
			$this->assertArrayHasKey( $field, $entity, $field );
		}
	}

	/**
	 * The label a shopper reads is assembled from the axes, so it cannot drift out of step with the
	 * `variation_types` that were asked for.
	 */
	public function test_the_title_is_the_axes_joined(): void {
		$this->with( array( 'variation_types' => array( 'material', 'flavor' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$entity = $this->entity();

			$this->assertSame( implode( ' / ', array_values( $entity['attributes'] ) ), $entity['title'] );
		}
	}

	public function test_the_price_is_minor_units(): void {
		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertIsInt( $this->entity()['price'] );
		}
	}

	// -----------------------------------------------------------------------------------
	// The parameters that used to be ignored.
	// -----------------------------------------------------------------------------------

	public function test_the_requested_axes_are_the_only_ones_used(): void {
		$this->with( array( 'variation_types' => array( 'weight' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertSame( array( 'Weight' ), array_keys( $this->entity()['attributes'] ) );
		}
	}

	public function test_an_unknown_axis_falls_back_to_size_and_colour(): void {
		$this->with( array( 'variation_types' => array( 'fit', 'pattern' ) ) );

		foreach ( range( 1, 20 ) as $ignored ) {
			foreach ( array_keys( $this->entity()['attributes'] ) as $axis ) {
				$this->assertContains( $axis, array( 'Size', 'Color' ) );
			}
		}
	}

	public function test_the_axis_count_is_honoured(): void {
		$this->with(
			array(
				'variation_types'        => array( 'size', 'color', 'material', 'style' ),
				'attributes_per_product' => array(
					'min' => 3,
					'max' => 3,
				),
			)
		);

		foreach ( range( 1, 20 ) as $ignored ) {
			$this->assertCount( 3, $this->entity()['attributes'] );
		}
	}

	/**
	 * More axes than there are types to fill them is a request nobody meant; the types available are
	 * the ceiling rather than a repeated axis or a run that fails.
	 */
	public function test_the_axis_count_cannot_exceed_the_types_offered(): void {
		$this->with(
			array(
				'variation_types'        => array( 'size' ),
				'attributes_per_product' => array(
					'min' => 4,
					'max' => 5,
				),
			)
		);

		foreach ( range( 1, 15 ) as $ignored ) {
			$this->assertCount( 1, $this->entity()['attributes'] );
		}
	}

	/**
	 * `variations_per_attribute` decides how wide the vocabulary is, which is the only way a
	 * generator producing one variation at a time can influence how many distinct values a product
	 * ends up carrying.
	 */
	public function test_the_vocabulary_width_is_honoured(): void {
		$this->with(
			array(
				'variation_types'          => array( 'size' ),
				'variations_per_attribute' => array(
					'min' => 2,
					'max' => 2,
				),
			)
		);

		$seen = array();

		foreach ( range( 1, 60 ) as $ignored ) {
			$seen[ (string) $this->entity()['attributes']['Size'] ] = true;
		}

		$this->assertCount( 2, $seen );
	}

	public function test_the_price_delta_range_is_honoured(): void {
		$this->with(
			array(
				'price_variation_range' => array(
					'min_percentage' => 10,
					'max_percentage' => 12,
				),
			)
		);

		foreach ( range( 1, 25 ) as $ignored ) {
			$delta = $this->entity()['price_delta_percent'];

			$this->assertGreaterThanOrEqual( 10, $delta );
			$this->assertLessThanOrEqual( 12, $delta );
		}
	}

	/**
	 * An all-positive range is a legitimate ask — every variation dearer than the base — and the
	 * endpoint used to reject it outright, because the minimum was capped at zero.
	 */
	public function test_a_negative_delta_is_still_possible(): void {
		$this->with(
			array(
				'price_variation_range' => array(
					'min_percentage' => -40,
					'max_percentage' => -30,
				),
			)
		);

		foreach ( range( 1, 15 ) as $ignored ) {
			$this->assertLessThan( 0, $this->entity()['price_delta_percent'] );
		}
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
	 * Three names for one switch is how none of them came to be read: the endpoint's
	 * `include_inventory`, the admin's `stock_settings.manage_stock`, and `inventory.manage_stock`,
	 * which is what Products already calls it. All three work, because two of them shipped.
	 */
	public function test_inventory_can_be_switched_off_by_any_of_its_three_names(): void {
		foreach (
			array(
				array( 'inventory' => array( 'manage_stock' => false ) ),
				array( 'stock_settings' => array( 'manage_stock' => false ) ),
				array( 'include_inventory' => false ),
			) as $params
		) {
			$this->with( $params );

			$entity = $this->entity();

			$this->assertFalse( $entity['manage_stock'], (string) wp_json_encode( $params ) );
			// No quantity at all rather than a quantity of zero, which every platform reads as out
			// of stock.
			$this->assertNull( $entity['stock'] );
		}
	}

	public function test_skus_can_be_switched_off(): void {
		$this->with( array( 'generate_skus' => false ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertNull( $this->entity()['sku'] );
		}

		$this->with( array( 'generate_skus' => true ) );

		$this->assertNotEmpty( $this->entity()['sku'] );
	}

	/**
	 * The generator still names no platform. The axes are the likeliest thing to break it: a
	 * WooCommerce attribute key is a slug, and Fluent Cart has no attribute model at all.
	 */
	public function test_the_entity_names_no_platform(): void {
		$serialised = (string) wp_json_encode( $this->entity() );

		foreach ( array( 'pa_', 'item_price', 'regular_price', 'other_info', 'serial_index' ) as $word ) {
			$this->assertStringNotContainsString( $word, $serialised, $word );
		}
	}
}
