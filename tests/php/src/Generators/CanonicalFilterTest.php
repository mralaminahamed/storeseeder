<?php
/**
 * Tests for the canonical-entity filters.
 *
 * Two filters wrap the same handoff — one for every resource, one per resource — and
 * the order is the contract: general first, specific last, so a resource can still
 * override what was applied to all of them.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Generators;

use StoreSeeder\Generation\Generators\Label;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Generation\Generator::generate_single_item
 */
class CanonicalFilterTest extends StoreSeederUnitTestCase {

	/**
	 * One label, generated the way the REST layer does it.
	 *
	 * The faker is created by set_faker(), not by the constructor, so a generator used
	 * without that call fails on an uninitialised typed property.
	 *
	 * @return void
	 */
	private function run_one(): void {
		$generator = new Label();
		$generator->set_locale( 'en_US' );
		$generator->set_faker();
		$generator->generate( 1 );
	}

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_canonical_entity' );
		remove_all_filters( 'storeseeder_canonical_label' );
		parent::tearDown();
	}

	public function test_general_filter_receives_entity_resource_and_generator(): void {
		$this->require_fluent_cart();

		$seen = array();

		add_filter(
			'storeseeder_canonical_entity',
			static function ( array $entity, string $resource_type, $generator ) use ( &$seen ): array {
				$seen[] = array( $resource_type, get_class( $generator ), array_keys( $entity ) );
				return $entity;
			},
			10,
			3
		);

		$this->run_one();

		$this->assertCount( 1, $seen );
		$this->assertSame( 'label', $seen[0][0] );
		$this->assertSame( Label::class, $seen[0][1] );
		$this->assertNotEmpty( $seen[0][2] );
	}

	/**
	 * The per-resource filter runs second, so its value is the one written.
	 */
	public function test_resource_filter_runs_after_the_general_one(): void {
		$this->require_fluent_cart();

		$order = array();

		add_filter(
			'storeseeder_canonical_entity',
			static function ( array $entity ) use ( &$order ): array {
				$order[] = 'general';
				return $entity;
			}
		);

		add_filter(
			'storeseeder_canonical_label',
			static function ( array $entity ) use ( &$order ): array {
				$order[] = 'resource';
				return $entity;
			}
		);

		$this->run_one();

		$this->assertSame( array( 'general', 'resource' ), $order );
	}

	/**
	 * A value set on every entity has to survive as far as the writer, which is the
	 * whole reason for the general filter — stamping a run id on everything generated
	 * should not need seventeen callbacks.
	 */
	public function test_general_filter_changes_what_is_written(): void {
		$this->require_fluent_cart();

		add_filter(
			'storeseeder_canonical_entity',
			static function ( array $entity ): array {
				if ( isset( $entity['title'] ) ) {
					$entity['title'] = 'Filtered title';
				}

				return $entity;
			}
		);

		$written = null;

		add_filter(
			'storeseeder_canonical_label',
			static function ( array $entity ) use ( &$written ): array {
				$written = $entity;
				return $entity;
			}
		);

		$this->run_one();

		$this->assertIsArray( $written );

		if ( isset( $written['title'] ) ) {
			$this->assertSame( 'Filtered title', $written['title'] );
		}
	}
}
