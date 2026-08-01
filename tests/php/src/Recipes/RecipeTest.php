<?php
/**
 * Tests for the recipe manifest.
 *
 * A manifest can come from a third-party plugin through `storeseeder_recipes`, so every one of
 * these is really the same question asked about a different field: does a malformed recipe get
 * dropped, or does it take the admin down with it?
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Recipes;

use StoreSeeder\Platforms\Resource;
use StoreSeeder\Recipes\Recipe;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Recipes\Recipe
 */
class RecipeTest extends StoreSeederUnitTestCase {

	/**
	 * Manifest assertions need neither the REST server nor a database fixture.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = true;

	/**
	 * A manifest with everything filled in, for a test to spoil one field of.
	 *
	 * @param array<string, mixed> $overrides Fields to replace.
	 *
	 * @return array<string, mixed>
	 */
	private function manifest( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'          => 'grocery',
				'name'        => 'Corner grocer',
				'description' => 'Fresh, chilled and pantry.',
				'icon'        => 'cart',
				'accent'      => 'green',
				'locales'     => array( 'en_US' ),
				'plan'        => array(
					array(
						'resource' => Resource::BRAND,
						'count'    => 22,
					),
					array(
						'resource' => Resource::PRODUCT,
						'count'    => 180,
					),
				),
				'params'      => array(
					'product' => array(
						'price_range' => array(
							'min' => 0.79,
							'max' => 42.5,
						),
					),
				),
			),
			$overrides
		);
	}

	// -----------------------------------------------------------------------------------
	// What a good manifest yields.
	// -----------------------------------------------------------------------------------

	public function test_a_complete_manifest_parses(): void {
		$recipe = Recipe::from_manifest( $this->manifest() );

		$this->assertInstanceOf( Recipe::class, $recipe );
		$this->assertSame( 'grocery', $recipe->id() );
		$this->assertSame( 'Corner grocer', $recipe->name() );
		$this->assertSame( 'green', $recipe->accent() );
		$this->assertCount( 2, $recipe->plan() );
	}

	/**
	 * The plan's order is the dependency order — brands before products, products before orders —
	 * so anything that reorders it produces orders with no line items.
	 */
	public function test_the_plan_keeps_its_order(): void {
		$recipe = Recipe::from_manifest( $this->manifest() );

		$this->assertNotNull( $recipe );
		$this->assertSame(
			array( Resource::BRAND, Resource::PRODUCT ),
			array_column( $recipe->plan(), 'resource' )
		);
	}

	public function test_params_reach_the_resource_that_asked_for_them(): void {
		$recipe = Recipe::from_manifest( $this->manifest() );

		$this->assertNotNull( $recipe );
		$this->assertSame(
			array(
				'min' => 0.79,
				'max' => 42.5,
			),
			$recipe->params_for( Resource::PRODUCT )['price_range']
		);
		$this->assertSame( array(), $recipe->params_for( Resource::ORDER ) );
	}

	public function test_a_locale_it_ships_is_reported_as_shipped(): void {
		$recipe = Recipe::from_manifest( $this->manifest( array( 'locales' => array( 'en_US', 'de_DE' ) ) ) );

		$this->assertNotNull( $recipe );
		$this->assertTrue( $recipe->has_locale( 'de_DE' ) );
		$this->assertFalse( $recipe->has_locale( 'ja_JP' ) );
	}

	// -----------------------------------------------------------------------------------
	// What a bad manifest yields: null, never a fatal.
	// -----------------------------------------------------------------------------------

	/**
	 * @dataProvider unusable_manifests
	 *
	 * @param mixed  $manifest The manifest.
	 * @param string $why      What is wrong with it.
	 */
	public function test_an_unusable_manifest_is_dropped( $manifest, string $why ): void {
		$this->assertNull( Recipe::from_manifest( $manifest ), $why );
	}

	/**
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public function unusable_manifests(): array {
		return array(
			'not an array'    => array( 'grocery', 'a string is not a manifest' ),
			'no id'           => array( array( 'name' => 'X' ), 'without an id there is no directory to read' ),
			'no name'         => array(
				array(
					'id'   => 'x',
					'plan' => array( array( 'resource' => Resource::PRODUCT, 'count' => 1 ) ),
				),
				'a card with no name is not a card',
			),
			'empty plan'      => array(
				array(
					'id'   => 'x',
					'name' => 'X',
					'plan' => array(),
				),
				'a recipe that creates nothing is a button that does nothing',
			),
			'plan all bogus'  => array(
				array(
					'id'   => 'x',
					'name' => 'X',
					'plan' => array( array( 'resource' => 'unicorn', 'count' => 5 ) ),
				),
				'every line dropped leaves an empty plan',
			),
		);
	}

	/**
	 * The id becomes a path segment, so it is the one field where a bad value is not merely
	 * cosmetic. `sanitize_key` forbids a separator and the round-trip check forbids the rest.
	 *
	 * @dataProvider traversal_ids
	 *
	 * @param string $id The id to try.
	 */
	public function test_an_id_that_could_escape_its_directory_is_refused( string $id ): void {
		$this->assertNull( Recipe::from_manifest( $this->manifest( array( 'id' => $id ) ) ) );
	}

	/**
	 * @return array<int, array{0: string}>
	 */
	public function traversal_ids(): array {
		return array(
			array( '../../etc' ),
			array( '..' ),
			array( 'a/b' ),
			array( 'a\\b' ),
			array( 'Grocery' ),
			array( 'groc ery' ),
			array( '' ),
		);
	}

	// -----------------------------------------------------------------------------------
	// Fields that degrade rather than fail.
	// -----------------------------------------------------------------------------------

	/**
	 * An accent outside the token set would sit outside the theme, so it falls back rather than
	 * reaching the stylesheet. A third-party recipe must not be able to break either theme.
	 */
	public function test_an_unknown_accent_falls_back(): void {
		foreach ( array( '#ff0000', 'chartreuse', '', 'var(--accent); }' ) as $accent ) {
			$recipe = Recipe::from_manifest( $this->manifest( array( 'accent' => $accent ) ) );

			$this->assertNotNull( $recipe, $accent );
			$this->assertSame( 'accent', $recipe->accent(), $accent );
		}
	}

	public function test_an_unknown_resource_loses_its_line_and_keeps_the_rest(): void {
		$recipe = Recipe::from_manifest(
			$this->manifest(
				array(
					'plan' => array(
						array( 'resource' => 'unicorn', 'count' => 4 ),
						array( 'resource' => Resource::PRODUCT, 'count' => 12 ),
					),
				)
			)
		);

		$this->assertNotNull( $recipe );
		$this->assertSame( array( Resource::PRODUCT ), array_column( $recipe->plan(), 'resource' ) );
	}

	/**
	 * Two lines for one resource would run the generator twice and produce double what the card
	 * promised — the counts on the card are the only preview this page has.
	 */
	public function test_a_repeated_resource_is_counted_once(): void {
		$recipe = Recipe::from_manifest(
			$this->manifest(
				array(
					'plan' => array(
						array( 'resource' => Resource::PRODUCT, 'count' => 10 ),
						array( 'resource' => Resource::PRODUCT, 'count' => 90 ),
					),
				)
			)
		);

		$this->assertNotNull( $recipe );
		$this->assertCount( 1, $recipe->plan() );
		$this->assertSame( 10, $recipe->plan()[0]['count'] );
	}

	public function test_a_zero_or_negative_count_loses_its_line(): void {
		$recipe = Recipe::from_manifest(
			$this->manifest(
				array(
					'plan' => array(
						array( 'resource' => Resource::BRAND, 'count' => 0 ),
						array( 'resource' => Resource::CUSTOMER, 'count' => -5 ),
						array( 'resource' => Resource::PRODUCT, 'count' => 3 ),
					),
				)
			)
		);

		$this->assertNotNull( $recipe );
		$this->assertSame( array( Resource::PRODUCT ), array_column( $recipe->plan(), 'resource' ) );
	}

	public function test_a_missing_locale_list_becomes_the_one_every_recipe_ships(): void {
		foreach ( array( array(), 'en_US', null ) as $locales ) {
			$recipe = Recipe::from_manifest( $this->manifest( array( 'locales' => $locales ) ) );

			$this->assertNotNull( $recipe );
			$this->assertSame( array( 'en_US' ), $recipe->locales() );
		}
	}

	/**
	 * The plan is annotated per request, because whether a platform supports a resource depends on
	 * the target — so the manifest must not carry a cached answer.
	 */
	public function test_the_payload_carries_no_capability_answer(): void {
		$recipe = Recipe::from_manifest( $this->manifest() );

		$this->assertNotNull( $recipe );

		foreach ( $recipe->to_array()['plan'] as $entry ) {
			$this->assertArrayNotHasKey( 'supported', $entry );
		}
	}
}
