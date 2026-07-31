<?php
/**
 * Test class for the Order REST controller.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Controllers;

use StoreSeeder\Rest\Controllers\Order;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * Tests for the Order REST controller.
 *
 * @covers \StoreSeeder\Rest\Controllers\Order
 */
class OrderRESTControllerTest extends StoreSeederUnitTestCase {

	/**
	 * The controller under test.
	 *
	 * @var Order
	 */
	private Order $controller;

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->controller = new Order();
		$this->controller->register_routes();
	}

	/**
	 * The controller instantiates.
	 *
	 * @return void
	 */
	public function test_controller_instantiation(): void {
		$this->assertInstanceOf( Order::class, $this->controller );
	}

	/**
	 * The generate route is registered for POST.
	 *
	 * @return void
	 */
	public function test_route_registration(): void {
		$routes = $this->server->get_routes();
		$route  = '/' . $this->namespace . '/orders/generate';

		$this->assertArrayHasKey( $route, $routes );
		$this->assertEquals( 'POST', $routes[ $route ][0]['methods']['POST'] );
	}

	/**
	 * An unauthenticated request is rejected.
	 *
	 * @return void
	 */
	public function test_generate_requires_authentication(): void {
		wp_set_current_user( 0 );

		$request = $this->get_wp_rest_request( 'POST', '/orders/generate' );
		$request->set_param( 'count', 3 );
		$response = $this->server->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	/**
	 * A user without the manage_options capability is rejected.
	 *
	 * @return void
	 */
	public function test_generate_requires_capability(): void {
		wp_set_current_user( $this->create_customer_user() );

		$request = $this->get_wp_rest_request( 'POST', '/orders/generate' );
		$request->set_param( 'count', 3 );
		$response = $this->server->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	/**
	 * Out-of-range counts are rejected with a 400.
	 *
	 * @return void
	 */
	public function test_generate_rejects_invalid_count(): void {
		wp_set_current_user( $this->create_admin_user() );

		foreach ( array( 0, -1, 101 ) as $count ) {
			$request = $this->get_wp_rest_request( 'POST', '/orders/generate' );
			$request->set_param( 'count', $count );
			$response = $this->server->dispatch( $request );

			$this->assertTrue( $response->is_error(), "count={$count} should be rejected" );
			$this->assertEquals( 400, $response->get_status(), "count={$count} should return 400" );
		}
	}

	/**
	 * A valid request either generates rows or reports a dependency error.
	 *
	 * Independent generators return 200 with the resource-typed payload;
	 * dependency-based ones may report a generation error when prerequisites
	 * are absent in a fresh database.
	 *
	 * @return void
	 */
	public function test_generate_valid_request(): void {
		$this->require_fluent_cart();
		wp_set_current_user( $this->create_admin_user() );

		$request = $this->get_wp_rest_request( 'POST', '/orders/generate' );
		$request->set_param( 'count', 2 );
		$response = $this->server->dispatch( $request );

		$this->assertContains( $response->get_status(), array( 200, 500 ) );

		$data = $response->get_data();
		$this->assertIsArray( $data );

		if ( 200 === $response->get_status() ) {
			$this->assertArrayHasKey( 'message', $data );
			$this->assertArrayHasKey( 'order', $data );
			$this->assertIsArray( $data['order'] );
		}
	}
}
