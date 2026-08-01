<?php
/**
 * The generator and the registry must agree on where a recipe's words are.
 *
 * They did not, once. Recipes moved out of the plugin and into the uploads directory; the registry
 * followed and `Generator::sample_data_candidates()` did not. Every recipe then ran to completion on
 * default vocabulary — a "grocery" store full of consumer electronics, reported as a success, with
 * the audit saying nothing because it read the *correct* path while the generator read the stale
 * one.
 *
 * That is the shape of failure this file exists for: not a crash, but two computations of one path
 * that quietly stop matching. So the assertion is the agreement itself, not any particular
 * directory.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Recipes;

use StoreSeeder\Generation\Generators\Product_Tag;
use StoreSeeder\Recipes\Registry;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generator::sample_data_candidates
 */
class VocabularyPathTest extends StoreSeederUnitTestCase {

	/**
	 * Paths and filters only.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	/**
	 * A throwaway directory standing in for a downloaded archive.
	 *
	 * @var string
	 */
	private string $root = '';

	public function setUp(): void {
		parent::setUp();

		$this->root = trailingslashit( sys_get_temp_dir() ) . 'storeseeder-vocab-' . wp_generate_password( 8, false );

		wp_mkdir_p( $this->root . '/grocery/product_tags/en_US' );
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
			$this->root . '/grocery/product_tags/en_US/labels.json',
			'{"labels":["Organic","Vegan"]}'
		);

		add_filter( 'storeseeder_recipes', array( $this, 'register_grocery' ) );
		add_filter( 'storeseeder_recipe_directories', array( $this, 'point_at_fixture' ) );

		Registry::instance()->reset();
	}

	public function tear_down() {
		remove_filter( 'storeseeder_recipes', array( $this, 'register_grocery' ) );
		remove_filter( 'storeseeder_recipe_directories', array( $this, 'point_at_fixture' ) );

		$this->discard( $this->root );

		Registry::instance()->reset();

		parent::tear_down();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function register_grocery(): array {
		return array(
			array(
				'id'   => 'grocery',
				'name' => 'Corner grocer',
				'plan' => array(
					array(
						'resource' => 'product',
						'count'    => 10,
					),
				),
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function point_at_fixture(): array {
		return array( 'grocery' => $this->root . '/grocery' );
	}

	/**
	 * Not called `rmdir`: `WP_UnitTestCase_Base` declares a public one, and narrowing it to private
	 * is a compile-time fatal that PHPUnit reports as exit 255 with no message.
	 *
	 * @param string $dir Directory to remove.
	 *
	 * @return void
	 */
	private function discard( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( array_diff( (array) scandir( $dir ), array( '.', '..' ) ) as $entry ) {
			$path = $dir . '/' . $entry;

			is_dir( $path ) ? $this->discard( $path ) : unlink( $path );
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture.
	}

	/**
	 * @param array<string, mixed> $params Generation parameters.
	 */
	private function tag_generator( array $params ): Product_Tag {
		$generator = new Product_Tag();

		$generator->set_locale( 'en_US' );
		$generator->set_faker();
		$generator->set_generation_params( $params );

		return $generator;
	}

	/**
	 * @param array<string, mixed> $params Generation parameters.
	 *
	 * @return string[]
	 */
	private function candidates( array $params ): array {
		$method = new \ReflectionMethod( Product_Tag::class, 'sample_data_candidates' );

		return (array) $method->invoke( $this->tag_generator( $params ), 'product_tags', 'labels' );
	}

	/**
	 * @param array<string, mixed> $params Generation parameters.
	 *
	 * @return string[]
	 */
	private function labels( array $params ): array {
		$method = new \ReflectionMethod( Product_Tag::class, 'labels' );

		return (array) $method->invoke( $this->tag_generator( $params ) );
	}

	// -----------------------------------------------------------------------------------
	// The agreement.
	// -----------------------------------------------------------------------------------

	/**
	 * The assertion that would have caught the bug: whatever the registry says is the recipe's
	 * directory, the generator looks inside *that*, not somewhere it computed for itself.
	 */
	public function test_the_generator_looks_where_the_registry_says(): void {
		$expected = Registry::vocabulary_directory( 'grocery' ) . '/product_tags/en_US/labels.json';

		$this->assertContains( $expected, $this->candidates( array( 'recipe' => 'grocery' ) ) );
	}

	/**
	 * And the words actually arrive. The path agreeing is not enough on its own — the previous
	 * failure was silent precisely because a miss falls back rather than erroring.
	 */
	public function test_the_recipe_vocabulary_is_the_one_used(): void {
		$this->assertSame( array( 'Organic', 'Vegan' ), $this->labels( array( 'recipe' => 'grocery' ) ) );
	}

	/**
	 * The recipe's own file is tried before anything shared, or a recipe could not override.
	 */
	public function test_the_recipe_is_preferred_over_the_shared_vocabulary(): void {
		$candidates = $this->candidates( array( 'recipe' => 'grocery' ) );
		$recipe_dir = Registry::vocabulary_directory( 'grocery' );

		$this->assertStringStartsWith( $recipe_dir, $candidates[0] );
	}

	/**
	 * No recipe means no recipe candidates — not a path containing an empty segment, which would
	 * resolve to a directory that is nobody's.
	 */
	public function test_no_recipe_contributes_no_recipe_candidates(): void {
		foreach ( $this->candidates( array() ) as $candidate ) {
			$this->assertStringNotContainsString( 'storeseeder-recipes', $candidate );
			$this->assertStringNotContainsString( '//', str_replace( 'http://', '', $candidate ) );
		}
	}

	/**
	 * A recipe nobody registered is discarded before it reaches a path, so a stale saved
	 * configuration cannot point the loader anywhere.
	 */
	public function test_an_unknown_recipe_contributes_nothing(): void {
		$this->assertSame(
			$this->candidates( array() ),
			$this->candidates( array( 'recipe' => 'no-such-recipe' ) )
		);
	}

	/**
	 * The locale falls back within the recipe before leaving it: a recipe that ships `en_US` and is
	 * asked for German should serve its own English words rather than the shared vocabulary's.
	 */
	public function test_the_locale_falls_back_inside_the_recipe_first(): void {
		$generator = $this->tag_generator( array( 'recipe' => 'grocery' ) );
		$generator->set_locale( 'de_DE' );

		$method = new \ReflectionMethod( Product_Tag::class, 'sample_data_candidates' );

		$candidates = (array) $method->invoke( $generator, 'product_tags', 'labels' );
		$recipe_dir = Registry::vocabulary_directory( 'grocery' );

		$this->assertStringStartsWith( $recipe_dir, $candidates[0] );
		$this->assertStringContainsString( '/de_DE/', $candidates[0] );
		$this->assertStringStartsWith( $recipe_dir, $candidates[1] );
		$this->assertStringContainsString( '/en_US/', $candidates[1] );
	}
}
