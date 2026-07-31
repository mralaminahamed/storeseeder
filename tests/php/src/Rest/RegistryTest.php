<?php
/**
 * Tests for the REST controller registry.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Rest;

use StoreSeeder\Rest\Registry;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Rest\Registry
 */
class RegistryTest extends StoreSeederUnitTestCase {

	public function setUp(): void {
		parent::setUp();
		Registry::reset();
	}

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_rest_controllers' );
		Registry::reset();
		parent::tearDown();
	}

	public function test_all_seventeen_controllers_are_registered(): void {
		$this->assertCount( 17, Registry::instance()->all() );
	}

	/**
	 * The registry keys by REST base, which is the thing routes are built from — so
	 * this also guards the hyphen/underscore inconsistency that catches people out.
	 */
	public function test_controllers_are_keyed_by_rest_base(): void {
		$bases = array_keys( Registry::instance()->all() );

		foreach ( array( 'products', 'cart-sessions', 'tax_classes', 'order_tax_rates' ) as $base ) {
			$this->assertContains( $base, $bases );
		}
	}

	public function test_get_returns_a_controller_by_base(): void {
		$controller = Registry::instance()->get( 'products' );

		$this->assertNotNull( $controller );
		$this->assertSame( 'products', $controller->rest_base() );
	}

	public function test_get_returns_null_for_an_unknown_base(): void {
		$this->assertNull( Registry::instance()->get( 'nope' ) );
	}

	public function test_register_routes_registers_every_controller(): void {
		do_action( 'rest_api_init' );
		Registry::instance()->register_routes();

		$routes = rest_get_server()->get_routes();

		foreach ( Registry::instance()->all() as $base => $controller ) {
			$this->assertArrayHasKey( "/storeseeder/v1/{$base}/generate", $routes, $base );
			$this->assertArrayHasKey( "/storeseeder/v1/{$base}/preview", $routes, $base );
		}
	}

	/**
	 * The point of the registry: a platform driver shipped from another plugin can
	 * expose a resource of its own without patching this one.
	 */
	public function test_filter_can_add_a_controller(): void {
		add_filter(
			'storeseeder_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = new StubController();
				return $controllers;
			}
		);

		$all = Registry::instance()->all();

		$this->assertArrayHasKey( 'stub-things', $all );
		$this->assertCount( 18, $all );
	}

	public function test_filter_can_remove_a_controller(): void {
		add_filter(
			'storeseeder_rest_controllers',
			static function ( array $controllers ): array {
				return array_values(
					array_filter(
						$controllers,
						static function ( $c ): bool {
							return \StoreSeeder\Rest\Controllers\Subscription::class !== $c;
						}
					)
				);
			}
		);

		$this->assertNull( Registry::instance()->get( 'subscriptions' ) );
		$this->assertCount( 16, Registry::instance()->all() );
	}

	/**
	 * A filter is third-party code and may return anything. A bad entry must be
	 * dropped rather than fataling the REST API, which is registered from the same
	 * call path.
	 */
	public function test_malformed_entries_are_discarded(): void {
		add_filter(
			'storeseeder_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = 'Not\\A\\Class';
				$controllers[] = new \stdClass();
				$controllers[] = 42;
				return $controllers;
			}
		);

		$this->assertCount( 17, Registry::instance()->all() );
	}

	/**
	 * Instantiating a controller must have no side effects. It used to add a
	 * rest_api_init listener in its constructor, which meant building the set to
	 * inspect it also registered it.
	 */
	public function test_constructing_a_controller_registers_nothing(): void {
		$before = count( rest_get_server()->get_routes() );

		new \StoreSeeder\Rest\Controllers\Product();

		do_action( 'rest_api_init' );

		$this->assertSame( $before, count( rest_get_server()->get_routes() ) );
	}
}
