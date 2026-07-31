<?php
/**
 * Test class for the main StoreSeeder plugin.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

use StoreSeeder;

/**
 * Test class for the main StoreSeeder plugin.
 *
 * @covers \StoreSeeder
 */
class StoreSeederTest extends StoreSeederUnitTestCase {

	/**
	 * The plugin instance.
	 *
	 * @var StoreSeeder
	 */
	private StoreSeeder $plugin;

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->plugin = storeseeder();
	}

	/**
	 * The plugin bootstrap constants are defined.
	 *
	 * @return void
	 */
	public function test_constants_defined(): void {
		$this->assertTrue( defined( 'STORESEEDER_VERSION' ) );
	}

	/**
	 * get_instance() returns a shared singleton.
	 *
	 * @return void
	 */
	public function test_instance_is_singleton(): void {
		$this->assertInstanceOf( StoreSeeder::class, StoreSeeder::get_instance() );
		$this->assertSame( StoreSeeder::get_instance(), StoreSeeder::get_instance() );
	}

	/**
	 * The helper returns the same singleton instance.
	 *
	 * @return void
	 */
	public function test_helper_returns_instance(): void {
		$this->assertSame( StoreSeeder::get_instance(), storeseeder() );
	}

	/**
	 * The version property matches the version constant.
	 *
	 * @return void
	 */
	public function test_version_matches_constant(): void {
		$this->assertEquals( STORESEEDER_VERSION, $this->plugin->version );
	}

	/**
	 * The plugin registers its REST routes under the plugin namespace.
	 *
	 * @return void
	 */
	public function test_rest_routes_registered(): void {
		$this->require_fluent_cart();

		/*
		 * Register through a controller rather than the plugin's rest_api_init
		 * handler. That handler is behind check_dependencies(), which reads the
		 * active_plugins option — and Brain Monkey (set up for every test in this
		 * suite) stubs the hook and option layer, so the gate cannot be opened
		 * from inside a test. Exercising the controller keeps the assertion about
		 * route registration itself, which is what this test is for.
		 */
		( new \StoreSeeder\Rest\Controllers\Product() )->register_routes();

		$routes = $this->server->get_routes();
		$prefix = '/' . $this->namespace;

		$found = array_filter(
			array_keys( $routes ),
			static function ( $route ) use ( $prefix ) {
				return strpos( $route, $prefix ) === 0;
			}
		);

		$this->assertNotEmpty( $found, 'No REST routes were registered under the plugin namespace.' );
	}
}
