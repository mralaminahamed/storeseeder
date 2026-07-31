<?php
/**
 * Tests for the MCP ability registry and the ability definitions.
 *
 * The MCP layer had no tests at all before this. It is optional at runtime, which is
 * exactly why a break in it would go unnoticed: with the Abilities API absent the
 * whole layer silently does nothing, so a malformed definition would only surface for
 * the users who have it installed.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\MCP;

use StoreSeeder\MCP\Ability;
use StoreSeeder\MCP\Registry;
use StoreSeeder\Rest\Registry as Rest_Registry;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\MCP\Registry
 * @covers \StoreSeeder\MCP\Ability
 */
class RegistryTest extends StoreSeederUnitTestCase {

	public function setUp(): void {
		parent::setUp();
		Registry::reset();
	}

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_mcp_abilities' );
		Registry::reset();
		parent::tearDown();
	}

	public function test_all_seventeen_abilities_are_registered(): void {
		$this->assertCount( 17, Registry::instance()->all() );
	}

	public function test_abilities_are_keyed_by_ability_id(): void {
		$ids = Registry::instance()->ids();

		$this->assertContains( 'storeseeder/generate-products', $ids );
		$this->assertContains( 'storeseeder/generate-cart-sessions', $ids );
		// Underscored REST bases become hyphenated ids.
		$this->assertContains( 'storeseeder/generate-tax-classes', $ids );
		$this->assertContains( 'storeseeder/generate-order-tax-rates', $ids );
	}

	/**
	 * The id is derived from REST_BASE so the two cannot disagree. If that derivation
	 * ever stops matching an endpoint, an ability would dispatch to a route that does
	 * not exist — and MCP being optional, quietly.
	 */
	public function test_every_ability_id_matches_a_real_rest_route(): void {
		$bases = array_keys( Rest_Registry::instance()->all() );

		foreach ( Registry::instance()->all() as $id => $class ) {
			// Pulled into a variable first: a class constant cannot be interpolated
			// into a string, and "{$class::REST_BASE}" is a parse error.
			$base = $class::REST_BASE;

			$this->assertContains(
				$base,
				$bases,
				"{$id} dispatches to /{$base}/generate, which no controller serves"
			);
		}
	}

	public function test_every_definition_is_well_formed(): void {
		foreach ( Registry::instance()->definitions() as $id => $def ) {
			foreach ( array( 'label', 'description', 'category', 'input_schema', 'output_schema', 'execute_callback', 'permission_callback' ) as $key ) {
				$this->assertArrayHasKey( $key, $def, "{$id} is missing {$key}" );
			}

			$this->assertNotSame( '', $def['label'], $id );
			$this->assertNotSame( '', $def['description'], $id );
			$this->assertSame( Ability::CATEGORY, $def['category'], $id );
			$this->assertIsCallable( $def['execute_callback'], $id );
			$this->assertIsCallable( $def['permission_callback'], $id );
		}
	}

	/**
	 * Every ability takes count, and count is the only required parameter — the
	 * contract an MCP client is told to satisfy.
	 */
	public function test_every_input_schema_requires_count_and_offers_locale_and_seed(): void {
		foreach ( Registry::instance()->definitions() as $id => $def ) {
			$schema = $def['input_schema'];

			$this->assertSame( 'object', $schema['type'], $id );
			$this->assertSame( array( 'count' ), $schema['required'], $id );

			foreach ( array( 'count', 'locale', 'seed' ) as $common ) {
				$this->assertArrayHasKey( $common, $schema['properties'], "{$id} is missing {$common}" );
			}

			// The REST layer caps a run at 100; the schema must not invite more.
			$this->assertSame( 100, $schema['properties']['count']['maximum'], $id );
		}
	}

	public function test_every_output_schema_declares_its_envelope(): void {
		foreach ( Registry::instance()->definitions() as $id => $def ) {
			$props = $def['output_schema']['properties'];

			$this->assertArrayHasKey( 'message', $props, $id );
			// Exactly one key besides `message`, and it holds the items array.
			$items = array_diff_key( $props, array( 'message' => true ) );
			$this->assertCount( 1, $items, $id );
			$this->assertSame( 'array', reset( $items )['type'], $id );
		}
	}

	public function test_filter_can_add_an_ability(): void {
		add_filter(
			'storeseeder_mcp_abilities',
			static function ( array $abilities ): array {
				$abilities[] = StubAbility::class;
				return $abilities;
			}
		);

		$all = Registry::instance()->all();

		$this->assertArrayHasKey( 'storeseeder/generate-stub-things', $all );
		$this->assertCount( 18, $all );
	}

	public function test_filter_can_remove_an_ability(): void {
		add_filter(
			'storeseeder_mcp_abilities',
			static function ( array $abilities ): array {
				return array_values(
					array_filter(
						$abilities,
						static function ( $a ): bool {
							return \StoreSeeder\MCP\Abilities\Generate_Subscriptions::class !== $a;
						}
					)
				);
			}
		);

		$this->assertNotContains( 'storeseeder/generate-subscriptions', Registry::instance()->ids() );
		$this->assertCount( 16, Registry::instance()->all() );
	}

	public function test_malformed_entries_are_discarded(): void {
		add_filter(
			'storeseeder_mcp_abilities',
			static function ( array $abilities ): array {
				$abilities[] = 'Not\\A\\Class';
				$abilities[] = new \stdClass();
				$abilities[] = 42;
				// A real class, but not an Ability.
				$abilities[] = Registry::class;
				return $abilities;
			}
		);

		$this->assertCount( 17, Registry::instance()->all() );
	}
}
