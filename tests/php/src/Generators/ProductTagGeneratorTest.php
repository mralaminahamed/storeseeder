<?php
/**
 * Tests for the product tag generator, and for one shipped driver refusing the resource.
 *
 * Tags are the first resource where the two shipped drivers disagree: WooCommerce registers
 * `product_tag`, and Fluent Cart registers no tag taxonomy at all. So the assertions worth having
 * are that WooCommerce writes one, and that Fluent Cart says why it cannot rather than shipping a
 * writer that quietly creates terms nothing reads.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generators;

use Faker\Factory;
use StoreSeeder\Generation\Generators\Product_Tag;
use StoreSeeder\Platforms\Registry;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generators\Product_Tag
 */
class ProductTagGeneratorTest extends StoreSeederUnitTestCase {

	/**
	 * The generator under test.
	 *
	 * @var Brand
	 */
	private Product_Tag $generator;

	/**
	 * Neither the REST server nor a database fixture is needed for the entity assertions; the
	 * writer cases ask for their platform themselves.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	public function setUp(): void {
		parent::setUp();

		$this->generator = new Product_Tag();

		// The faker is created per run in production, after the locale is set — so a test that
		// builds an entity has to do the same or it reads an uninitialised property.
		$this->generator->set_locale( 'en_US' );
		$this->generator->set_faker();
	}

	public function test_it_reports_its_resource_and_type(): void {
		$this->assertSame(
			array( 'product_tags' => 'Product Tags' ),
			$this->generator->get_supported_types()
		);
		$this->assertNotSame( '', $this->generator->get_description() );
	}

	public function test_product_tag_is_a_canonical_resource(): void {
		$this->assertTrue( Resource::exists( Resource::PRODUCT_TAG ) );
		$this->assertContains( Resource::PRODUCT_TAG, Resource::all() );
	}

	/**
	 * A tag has to outlive the products carrying it, the same as the other taxonomies:
	 * `Purge::order()` reverses `Resource::all()`, so the tag belongs *before* product there.
	 */
	public function test_tags_are_deleted_after_the_products_that_carry_them(): void {
		$order = \StoreSeeder\Generation\Purge::order();

		$this->assertGreaterThan(
			array_search( Resource::PRODUCT, $order, true ),
			array_search( Resource::PRODUCT_TAG, $order, true )
		);
	}

	public function test_preview_describes_the_columns_it_returns(): void {
		$preview = $this->generator->preview( 3 );

		$this->assertCount( 3, $preview['rows'] );
		$this->assertSame(
			array( 'name', 'slug', 'products' ),
			wp_list_pluck( $preview['columns'], 'key' )
		);

		foreach ( $preview['rows'] as $row ) {
			foreach ( array( 'name', 'slug', 'products' ) as $key ) {
				$this->assertArrayHasKey( $key, $row );
				$this->assertArrayHasKey( 'v', $row[ $key ] );
			}
		}
	}

	/**
	 * The entity has to be platform-neutral, which for a taxonomy resource means naming no
	 * taxonomy — the difference between `product_brand` and `product-brands` is the writer's
	 * to know.
	 */
	public function test_the_entity_names_no_taxonomy(): void {
		$entity = $this->build_entity();

		$this->assertSame(
			array( 'name', 'slug', 'description', 'link_count', 'nested' ),
			array_keys( $entity )
		);

		$serialised = wp_json_encode( $entity );

		$this->assertStringNotContainsString( 'product_tag', (string) $serialised );
	}

	public function test_the_slug_matches_the_name(): void {
		$entity = $this->build_entity();

		$this->assertSame( sanitize_title( (string) $entity['name'] ), $entity['slug'] );
	}

	public function test_products_per_tag_is_honoured_and_clamped(): void {
		$this->generator->set_generation_params( array( 'products_per_tag' => 7 ) );
		$this->assertSame( 7, $this->build_entity()['link_count'] );

		$this->generator->set_generation_params( array( 'products_per_tag' => 500 ) );
		$this->assertSame( 50, $this->build_entity()['link_count'] );

		// Zero is a real choice — a tag on nothing is what an empty archive needs.
		$this->generator->set_generation_params( array( 'products_per_tag' => 0 ) );
		$this->assertSame( 0, $this->build_entity()['link_count'] );
	}

	/**
	 * Tags are flat. `nested` is present and always false so the shared term writer, which also
	 * serves the hierarchical resources, has one entity shape to read rather than two — and a tag
	 * inside a tag is not something any platform models.
	 */
	public function test_tags_are_never_nested(): void {
		$this->generator->set_generation_params( array( 'nested_ratio' => 100 ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertFalse( $this->build_entity()['nested'] );
		}
	}

	// -----------------------------------------------------------------------------------
	// The writers.
	// -----------------------------------------------------------------------------------

	/**
	 * WooCommerce has tags, so it writes them.
	 */
	public function test_woocommerce_creates_the_term_in_its_tag_taxonomy(): void {
		$this->require_platform( 'woocommerce' );

		if ( ! taxonomy_exists( 'product_tag' ) ) {
			$this->markTestSkipped( 'product_tag is not registered in this environment.' );
		}

		$writer = Registry::instance()->get( 'woocommerce' )->writer( Resource::PRODUCT_TAG );

		$this->assertNotNull( $writer );
		$this->assertSame( Resource::PRODUCT_TAG, $writer->resource() );

		$writer->set_faker( Factory::create( 'en_US' ) );
		$writer->set_params( array() );

		$result = $writer->write(
			array(
				'name'        => 'Test Tag',
				'slug'        => 'test-tag',
				'description' => 'A tag for testing.',
				'link_count'  => 0,
				'nested'      => false,
			)
		);

		$this->assertNotWPError( $result );

		$term = get_term( (int) $result['id'], 'product_tag' );

		$this->assertInstanceOf( 'WP_Term', $term );
		$this->assertSame( 'Test Tag', $term->name );
		$this->assertSame( 0, (int) $term->parent );

		$this->assertTrue( $writer->delete( $result['id'] ) );
		$this->assertNull( get_term( (int) $result['id'], 'product_tag' ) );
	}

	/**
	 * Fluent Cart does not, and this is the assertion that keeps that honest: a driver with nowhere
	 * to put a resource has to refuse it *with a reason* and ship no writer. Registering the
	 * taxonomy ourselves would create terms that are real and unreachable from any Fluent Cart
	 * screen, which is worse than absent.
	 */
	public function test_fluent_cart_refuses_tags_with_a_reason(): void {
		$this->require_platform( 'fluent-cart' );

		$platform   = Registry::instance()->get( 'fluent-cart' );
		$capability = $platform->supports()[ Resource::PRODUCT_TAG ];

		$this->assertFalse( $capability->is_supported() );
		$this->assertStringContainsString( 'tag', $capability->get_reason() );
		// No plugin would change the answer, so nothing is named as installable.
		$this->assertSame( '', $capability->get_extension() );
		$this->assertNull( $platform->writer( Resource::PRODUCT_TAG ) );
	}

	/**
	 * The name pool is small, so a run of any size collides, and `wp_insert_term()` refuses a
	 * duplicate.
	 */
	public function test_a_duplicate_name_is_resolved_rather_than_failing(): void {
		$this->require_platform( 'woocommerce' );

		if ( ! taxonomy_exists( 'product_tag' ) ) {
			$this->markTestSkipped( 'product_tag is not registered in this environment.' );
		}

		$writer = Registry::instance()->get( 'woocommerce' )->writer( Resource::PRODUCT_TAG );
		$writer->set_faker( Factory::create( 'en_US' ) );
		$writer->set_params( array() );

		$entity = array(
			'name'        => 'Clashing Tag',
			'slug'        => 'clashing-tag',
			'description' => '',
			'link_count'  => 0,
			'nested'      => false,
		);

		$first  = $writer->write( $entity );
		$second = $writer->write( $entity );

		$this->assertNotWPError( $first );
		$this->assertNotWPError( $second );
		$this->assertNotSame( $first['name'], $second['name'] );
	}

	/**
	 * Build one entity through the generator's own preview-free path.
	 *
	 * @return array<string, mixed>
	 */
	private function build_entity(): array {
		// No setAccessible(): PHP 8.1 made reflection on a protected method work without it, and
		// 8.5 deprecates the call — which PHPUnit reports as unexpected output and fails on.
		$method = new \ReflectionMethod( $this->generator, 'build_entity' );

		return (array) $method->invoke( $this->generator );
	}
}
