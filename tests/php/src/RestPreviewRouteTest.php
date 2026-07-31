<?php
/**
 * Tests for the read-only preview endpoint every generator exposes.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

use StoreSeeder;
use WP_REST_Request;

/**
 * @covers \StoreSeeder\Rest\Controller
 * @covers \StoreSeeder\Generation\Generator
 */
class RestPreviewRouteTest extends StoreSeederUnitTestCase {

	/**
	 * Every generator's REST base, as registered.
	 *
	 * @var string[]
	 */
	private const ROUTES = array(
		'products',
		'customers',
		'orders',
		'coupons',
		'product-variations',
		'shipping-plans',
		'tax_classes',
		'transactions',
		'cart-sessions',
		'attributes',
		'refunds',
		'logs',
		'shipping_classes',
		'labels',
		'order_tax_rates',
		'product_downloads',
		'subscriptions',
	);

	public function setUp(): void {
		parent::setUp();
		$this->require_fluent_cart();
		wp_set_current_user( $this->create_admin_user() );
	}

	/**
	 * Register the plugin's controllers against the test server.
	 *
	 * The plugin's own rest_api_init handler sits behind a dependency gate that
	 * Brain Monkey's stubbed option layer makes unopenable from inside a test, so
	 * the controllers are registered directly.
	 *
	 * @return void
	 */
	private function register_controllers(): void {
		// Through the registry, so this test also proves the registry registers every
		// controller -- and so there is no second hardcoded list of seventeen here to
		// drift from the real one.
		\StoreSeeder\Rest\Registry::instance()->register_routes();
	}

	public function test_every_generator_registers_a_preview_route(): void {
		$this->register_controllers();
		$routes = $this->server->get_routes();

		foreach ( self::ROUTES as $route ) {
			$this->assertArrayHasKey(
				'/storeseeder/v1/' . $route . '/preview',
				$routes,
				$route . ' has no preview route, so the admin live preview would 404.'
			);
		}
	}

	public function test_preview_returns_columns_and_rows_for_every_generator(): void {
		$this->register_controllers();

		foreach ( self::ROUTES as $route ) {
			$request = new WP_REST_Request( 'POST', '/storeseeder/v1/' . $route . '/preview' );
			$request->set_param( 'count', 3 );
			$response = $this->server->dispatch( $request );

			$this->assertSame( 200, $response->get_status(), $route . ' preview did not return 200.' );

			$data = $response->get_data();

			$this->assertArrayHasKey( 'columns', $data, $route . ' preview is missing columns.' );
			$this->assertArrayHasKey( 'rows', $data, $route . ' preview is missing rows.' );
			$this->assertNotEmpty( $data['columns'], $route . ' preview has no columns.' );
			$this->assertCount( 3, $data['rows'], $route . ' preview returned the wrong row count.' );

			// Every row must key against the declared columns, or the table renders blanks.
			$keys = wp_list_pluck( $data['columns'], 'key' );

			foreach ( $data['rows'] as $row ) {
				foreach ( $keys as $key ) {
					$this->assertArrayHasKey( $key, $row, $route . " preview row is missing the '{$key}' cell." );
					$this->assertArrayHasKey( 'v', $row[ $key ], $route . " preview cell '{$key}' has no value." );
				}
			}
		}
	}

	public function test_preview_row_count_is_clamped(): void {
		$this->register_controllers();

		// 100 is the schema maximum for count; anything above is rejected by
		// validation before the callback runs, so this is the largest value that
		// actually reaches the clamp.
		$high = new WP_REST_Request( 'POST', '/storeseeder/v1/products/preview' );
		$high->set_param( 'count', 100 );
		$response = $this->server->dispatch( $high );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount(
			25,
			$response->get_data()['rows'],
			'A preview redraws on every keystroke, so the row count has an upper bound.'
		);
	}

	public function test_preview_rejects_a_count_outside_the_schema(): void {
		$this->register_controllers();

		$request = new WP_REST_Request( 'POST', '/storeseeder/v1/products/preview' );
		$request->set_param( 'count', 500 );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status(), 'Preview shares the generate parameter schema.' );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	public function test_generator_clamps_below_one(): void {
		// The route cannot deliver count=0 (schema minimum is 1), so the lower
		// clamp is exercised on the generator directly.
		$generator = new \StoreSeeder\Generation\Generators\Product();
		$generator->set_locale( 'en_US' );
		$generator->set_faker();
		$generator->set_generation_params( array() );

		$this->assertCount( 1, $generator->preview( 0 )['rows'] );
		$this->assertCount( 1, $generator->preview( -10 )['rows'] );
	}

	public function test_preview_persists_nothing(): void {
		$this->register_controllers();

		global $wpdb;
		$before = array(
			'posts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ),
			'meta'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ),
			'users' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ),
		);

		foreach ( self::ROUTES as $route ) {
			$request = new WP_REST_Request( 'POST', '/storeseeder/v1/' . $route . '/preview' );
			$request->set_param( 'count', 5 );
			$this->server->dispatch( $request );
		}

		$after = array(
			'posts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ),
			'meta'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ),
			'users' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ),
		);

		$this->assertSame( $before, $after, 'Preview must be read-only — it ran across every generator here.' );
	}

	public function test_preview_requires_the_manage_options_capability(): void {
		$this->register_controllers();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$request = new WP_REST_Request( 'POST', '/storeseeder/v1/products/preview' );
		$request->set_param( 'count', 3 );
		$response = $this->server->dispatch( $request );

		$this->assertContains(
			$response->get_status(),
			array( 401, 403 ),
			'Preview leaks generated shapes and runs work; it needs the same gate as generate.'
		);
	}

	public function test_core_generators_expose_resource_specific_columns(): void {
		$this->register_controllers();

		$expected = array(
			'products'  => array( 'name', 'sku', 'type', 'price', 'stock', 'status' ),
			'customers' => array( 'name', 'email', 'city', 'country', 'orders' ),
			'orders'    => array( 'number', 'customer', 'items', 'total', 'status' ),
			'coupons'   => array( 'code', 'type', 'amount', 'limit', 'status' ),
		);

		foreach ( $expected as $route => $keys ) {
			$request = new WP_REST_Request( 'POST', '/storeseeder/v1/' . $route . '/preview' );
			$request->set_param( 'count', 1 );
			$columns = $this->server->dispatch( $request )->get_data()['columns'];

			$this->assertSame(
				$keys,
				wp_list_pluck( $columns, 'key' ),
				$route . ' should describe its own resource rather than fall back to id/value.'
			);
		}
	}
}
