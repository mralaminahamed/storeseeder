<?php
/**
 * Test class for the main Fluent Cart FakerPress plugin.
 *
 * @package FluentCartFakerPress\Tests
 */

namespace FluentCartFakerPress\Tests;

use FluentCart_FakerPress;

/**
 * Test class for the main Fluent Cart FakerPress plugin.
 *
 * @covers \FluentCart_FakerPress
 */
class FluentCartFakerPressTest extends FluentCartFakerPressUnitTestCase {

	/**
	 * The plugin instance.
	 *
	 * @var FluentCart_FakerPress
	 */
	private FluentCart_FakerPress $plugin;

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->plugin = fluent_cart_fakerpress();
	}

	/**
	 * The plugin bootstrap constants are defined.
	 *
	 * @return void
	 */
	public function test_constants_defined(): void {
		$this->assertTrue( defined( 'FLUENT_CART_FAKERPRESS_VERSION' ) );
	}

	/**
	 * get_instance() returns a shared singleton.
	 *
	 * @return void
	 */
	public function test_instance_is_singleton(): void {
		$this->assertInstanceOf( FluentCart_FakerPress::class, FluentCart_FakerPress::get_instance() );
		$this->assertSame( FluentCart_FakerPress::get_instance(), FluentCart_FakerPress::get_instance() );
	}

	/**
	 * The helper returns the same singleton instance.
	 *
	 * @return void
	 */
	public function test_helper_returns_instance(): void {
		$this->assertSame( FluentCart_FakerPress::get_instance(), fluent_cart_fakerpress() );
	}

	/**
	 * The version property matches the version constant.
	 *
	 * @return void
	 */
	public function test_version_matches_constant(): void {
		$this->assertEquals( FLUENT_CART_FAKERPRESS_VERSION, $this->plugin->version );
	}

	/**
	 * The plugin registers its REST routes under the plugin namespace.
	 *
	 * @return void
	 */
	public function test_rest_routes_registered(): void {
		$this->require_fluent_cart();

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
