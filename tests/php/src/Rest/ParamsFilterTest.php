<?php
/**
 * Tests for the generation-parameter filters.
 *
 * There are two, general and per-endpoint, and the order between them is the contract:
 * the specific one runs last so it can still remove what the general one added.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Rest;

use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Rest\Controller::register_routes
 */
class ParamsFilterTest extends StoreSeederUnitTestCase {

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_rest_params' );
		remove_all_filters( 'storeseeder_rest_params_products' );
		parent::tearDown();
	}

	/**
	 * Re-register every route with the filters that this test added.
	 *
	 * The base class fires rest_api_init in setUp, so the routes exist before a test
	 * body runs. Registering again on the same server would append a second handler
	 * rather than replace the first, so the server is rebuilt.
	 *
	 * @return void
	 */
	private function reregister_routes(): void {
		global $wp_rest_server;

		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Args as the REST server holds them for the products generate route.
	 *
	 * @return array<string, mixed>
	 */
	private function registered_args(): array {
		$routes = rest_get_server()->get_routes( 'storeseeder/v1' );

		$this->assertArrayHasKey( '/storeseeder/v1/products/generate', $routes );

		return $routes['/storeseeder/v1/products/generate'][0]['args'];
	}

	public function test_general_filter_reaches_every_endpoint(): void {
		add_filter(
			'storeseeder_rest_params',
			static function ( array $params ): array {
				$params['dry_run'] = array( 'type' => 'boolean' );
				return $params;
			}
		);

		$this->reregister_routes();

		$routes = rest_get_server()->get_routes( 'storeseeder/v1' );
		$seen   = 0;

		foreach ( $routes as $route => $handlers ) {
			if ( 1 !== preg_match( '#/generate$#', $route ) ) {
				continue;
			}

			++$seen;
			$this->assertArrayHasKey( 'dry_run', $handlers[0]['args'], $route );
		}

		$this->assertSame( 17, $seen );
	}

	public function test_general_filter_receives_the_rest_base(): void {
		$bases = array();

		add_filter(
			'storeseeder_rest_params',
			static function ( array $params, string $rest_base ) use ( &$bases ): array {
				$bases[] = $rest_base;
				return $params;
			},
			10,
			2
		);

		$this->reregister_routes();

		$this->assertContains( 'products', $bases );
		$this->assertContains( 'cart-sessions', $bases );
		$this->assertCount( 17, array_unique( $bases ) );
	}

	/**
	 * The per-endpoint filter runs second, so one endpoint can opt out of something
	 * every endpoint was given.
	 */
	public function test_endpoint_filter_runs_after_the_general_one(): void {
		add_filter(
			'storeseeder_rest_params',
			static function ( array $params ): array {
				$params['dry_run'] = array( 'type' => 'boolean' );
				return $params;
			}
		);

		add_filter(
			'storeseeder_rest_params_products',
			static function ( array $params ): array {
				unset( $params['dry_run'] );
				return $params;
			}
		);

		$this->reregister_routes();

		$this->assertArrayNotHasKey( 'dry_run', $this->registered_args() );
	}

	/**
	 * Without a filter the schema still has to carry the common parameters — the
	 * general filter was inserted ahead of the existing one, and a mistake there
	 * would drop them.
	 */
	public function test_unfiltered_schema_is_unchanged(): void {
		$args = $this->registered_args();

		foreach ( array( 'count', 'locale', 'seed', 'platform' ) as $param ) {
			$this->assertArrayHasKey( $param, $args );
		}
	}
}
