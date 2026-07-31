<?php
/**
 * Tests for the platform registry.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Platform;

use StoreSeeder\Platforms\Registry;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Platforms\Registry
 */
class RegistryTest extends StoreSeederUnitTestCase {

	public function setUp(): void {
		parent::setUp();
		Registry::reset();
	}

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_platforms' );
		Registry::reset();
		parent::tearDown();
	}

	public function test_fluent_cart_is_registered_by_default(): void {
		$this->assertArrayHasKey( 'fluent-cart', Registry::instance()->all() );
	}

	public function test_filter_can_register_a_platform(): void {
		add_filter(
			'storeseeder_platforms',
			static function ( array $platforms ): array {
				$platforms[] = new StubPlatform( 'stub-cart' );
				return $platforms;
			}
		);

		$all = Registry::instance()->all();

		$this->assertArrayHasKey( 'stub-cart', $all );
		$this->assertSame( 'Stub stub-cart', $all['stub-cart']->label() );
	}

	public function test_get_returns_null_for_an_unknown_id(): void {
		$this->assertNull( Registry::instance()->get( 'nope' ) );
	}

	/**
	 * A filter is third-party code and may return anything. A bad entry must be
	 * dropped rather than fataling the admin menu, which is registered from the same
	 * call path.
	 */
	public function test_malformed_entries_are_discarded(): void {
		add_filter(
			'storeseeder_platforms',
			static function ( array $platforms ): array {
				$platforms[] = 'not a platform';
				$platforms[] = new \stdClass();
				return $platforms;
			}
		);

		$all = Registry::instance()->all();

		$this->assertArrayHasKey( 'fluent-cart', $all );
		$this->assertArrayHasKey( 'woocommerce', $all );
		// The two shipped drivers, and neither of the two junk entries.
		$this->assertCount( 2, $all );
	}

	public function test_active_excludes_inactive_platforms(): void {
		add_filter(
			'storeseeder_platforms',
			static function ( array $platforms ): array {
				$platforms[] = new StubPlatform( 'live-cart', true );
				$platforms[] = new StubPlatform( 'dead-cart', false );
				return $platforms;
			}
		);

		$active = Registry::instance()->active();

		$this->assertArrayHasKey( 'live-cart', $active );
		$this->assertArrayNotHasKey( 'dead-cart', $active );
		$this->assertTrue( Registry::instance()->has_active() );
	}

	public function test_labels_lists_every_registered_platform(): void {
		add_filter(
			'storeseeder_platforms',
			static function ( array $platforms ): array {
				$platforms[] = new StubPlatform( 'dead-cart', false );
				return $platforms;
			}
		);

		$labels = Registry::instance()->labels();

		// Inactive platforms are still named, because the dependency notice has to
		// tell people what they could install.
		$this->assertContains( 'Stub dead-cart', $labels );
		$this->assertContains( 'Fluent Cart', $labels );
	}
}
