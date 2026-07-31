<?php
/**
 * Tests for the product category generator and the writers that persist one.
 *
 * Categories have three spellings across the platforms — `product_cat` on WooCommerce and
 * EasyCommerce, `product-categories` on Fluent Cart — and the shortened WooCommerce one is the
 * trap: `product_category` is registered nowhere, so a writer using the obvious name would create
 * terms that no screen reads. So the assertions are about which taxonomy each writer lands in, and
 * that the generator names none of them.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generators;

use Faker\Factory;
use StoreSeeder\Generation\Generators\Product_Category;
use StoreSeeder\Platforms\Registry;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generators\Product_Category
 */
class ProductCategoryGeneratorTest extends StoreSeederUnitTestCase {

	/**
	 * The generator under test.
	 *
	 * @var Brand
	 */
	private Product_Category $generator;

	/**
	 * Neither the REST server nor a database fixture is needed for the entity assertions; the
	 * writer cases ask for their platform themselves.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	public function setUp(): void {
		parent::setUp();

		$this->generator = new Product_Category();

		// The faker is created per run in production, after the locale is set — so a test that
		// builds an entity has to do the same or it reads an uninitialised property.
		$this->generator->set_locale( 'en_US' );
		$this->generator->set_faker();
	}

	public function test_it_reports_its_resource_and_type(): void {
		$this->assertSame(
			array( 'product_categories' => 'Product Categories' ),
			$this->generator->get_supported_types()
		);
		$this->assertNotSame( '', $this->generator->get_description() );
	}

	public function test_product_category_is_a_canonical_resource(): void {
		$this->assertTrue( Resource::exists( Resource::PRODUCT_CATEGORY ) );
		$this->assertContains( Resource::PRODUCT_CATEGORY, Resource::all() );
	}

	/**
	 * A category has to outlive the products filed under it, or the cleanup removes the term
	 * first and the products are left pointing at nothing. `Purge::order()` reverses
	 * `Resource::all()`, so the category belongs *before* product in that list.
	 */
	public function test_categories_are_deleted_after_the_products_they_hold(): void {
		$order = \StoreSeeder\Generation\Purge::order();

		$this->assertGreaterThan(
			array_search( Resource::PRODUCT, $order, true ),
			array_search( Resource::PRODUCT_CATEGORY, $order, true )
		);
	}

	public function test_preview_describes_the_columns_it_returns(): void {
		$preview = $this->generator->preview( 3 );

		$this->assertCount( 3, $preview['rows'] );
		$this->assertSame(
			array( 'name', 'slug', 'products', 'nested' ),
			wp_list_pluck( $preview['columns'], 'key' )
		);

		foreach ( $preview['rows'] as $row ) {
			foreach ( array( 'name', 'slug', 'products', 'nested' ) as $key ) {
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

		$this->assertStringNotContainsString( 'product_cat', (string) $serialised );
		$this->assertStringNotContainsString( 'product-categories', (string) $serialised );
	}

	public function test_the_slug_matches_the_name(): void {
		$entity = $this->build_entity();

		$this->assertSame( sanitize_title( (string) $entity['name'] ), $entity['slug'] );
	}

	public function test_products_per_category_is_honoured_and_clamped(): void {
		$this->generator->set_generation_params( array( 'products_per_category' => 4 ) );
		$this->assertSame( 4, $this->build_entity()['link_count'] );

		$this->generator->set_generation_params( array( 'products_per_category' => 500 ) );
		$this->assertSame( 30, $this->build_entity()['link_count'] );

		// Zero is a real choice — a category with no products is what an empty archive needs.
		$this->generator->set_generation_params( array( 'products_per_category' => 0 ) );
		$this->assertSame( 0, $this->build_entity()['link_count'] );
	}

	/**
	 * The name pool switches with the flag: a top-level category is a department, a nested one a
	 * section. A sub-category called "Electronics" under "Apparel" is not a readable fixture.
	 */
	public function test_the_name_pool_follows_the_nesting_flag(): void {
		$this->generator->set_generation_params( array( 'nested_ratio' => 0 ) );
		$this->assertContains(
			$this->build_entity()['name'],
			array( 'Apparel', 'Electronics', 'Home & Kitchen', 'Outdoors', 'Beauty', 'Books', 'Toys & Games', 'Sports', 'Pet Supplies', 'Groceries', 'Office', 'Automotive', 'Garden', 'Health', 'Music' )
		);

		$this->generator->set_generation_params( array( 'nested_ratio' => 100 ) );
		$this->assertContains(
			$this->build_entity()['name'],
			array( 'Accessories', 'Bestsellers', 'Clearance', 'Essentials', 'Gifts', 'New Arrivals', 'Premium', 'Refurbished', 'Sale', 'Starter Kits' )
		);
	}

	public function test_nested_ratio_of_zero_never_nests(): void {
		$this->generator->set_generation_params( array( 'nested_ratio' => 0 ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertFalse( $this->build_entity()['nested'] );
		}
	}

	public function test_nested_ratio_of_one_hundred_always_nests(): void {
		$this->generator->set_generation_params( array( 'nested_ratio' => 100 ) );

		foreach ( range( 1, 10 ) as $ignored ) {
			$this->assertTrue( $this->build_entity()['nested'] );
		}
	}

	// -----------------------------------------------------------------------------------
	// The writers.
	// -----------------------------------------------------------------------------------

	/**
	 * Each driver's writer has to name its own taxonomy. Getting this wrong creates a term
	 * nothing on that platform reads, which looks like success.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function taxonomy_provider(): array {
		return array(
			'fluent-cart' => array( 'fluent-cart', 'product-categories' ),
			'woocommerce' => array( 'woocommerce', 'product_cat' ),
		);
	}

	/**
	 * @dataProvider taxonomy_provider
	 *
	 * @param string $platform_id Platform id.
	 * @param string $taxonomy    The taxonomy that platform keeps brands in.
	 */
	public function test_each_writer_creates_the_term_in_its_own_taxonomy( string $platform_id, string $taxonomy ): void {
		$this->require_platform( $platform_id );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			$this->markTestSkipped( sprintf( '%s is not registered in this environment.', $taxonomy ) );
		}

		$writer = Registry::instance()->get( $platform_id )->writer( Resource::PRODUCT_CATEGORY );

		$this->assertNotNull( $writer, $platform_id );
		$this->assertSame( Resource::PRODUCT_CATEGORY, $writer->resource() );

		$writer->set_faker( Factory::create( 'en_US' ) );
		$writer->set_params( array() );

		$result = $writer->write(
			array(
				'name'        => 'Test Category ' . $platform_id,
				'slug'        => 'test-category-' . $platform_id,
				'description' => 'A brand for testing.',
				'link_count'  => 0,
				'nested'      => false,
			)
		);

		$this->assertNotWPError( $result );

		$term = get_term( (int) $result['id'], $taxonomy );

		$this->assertInstanceOf( 'WP_Term', $term );
		$this->assertSame( 'Test Category ' . $platform_id, $term->name );

		// And gone again, with the taxonomy it belongs to.
		$this->assertTrue( $writer->delete( $result['id'] ) );
		$this->assertNull( get_term( (int) $result['id'], $taxonomy ) );
	}

	/**
	 * The pool of names is small, so a run of any size collides. `wp_insert_term()` refuses a
	 * duplicate, which would fail every item after the first few.
	 *
	 * @dataProvider taxonomy_provider
	 *
	 * @param string $platform_id Platform id.
	 * @param string $taxonomy    The taxonomy that platform keeps brands in.
	 */
	public function test_a_duplicate_name_is_resolved_rather_than_failing( string $platform_id, string $taxonomy ): void {
		$this->require_platform( $platform_id );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			$this->markTestSkipped( sprintf( '%s is not registered in this environment.', $taxonomy ) );
		}

		$writer = Registry::instance()->get( $platform_id )->writer( Resource::PRODUCT_CATEGORY );
		$writer->set_faker( Factory::create( 'en_US' ) );
		$writer->set_params( array() );

		$entity = array(
			'name'        => 'Clashing Category',
			'slug'        => 'clashing-category',
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
