<?php
/**
 * Base test case for all StoreSeeder tests.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

use Brain\Monkey;
use StoreSeeder\Platforms\Registry;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Base test case for all StoreSeeder tests.
 *
 * Provides REST helpers, user factories, and hook assertions shared across the
 * generator and controller test suites.
 *
 * @since 1.0.0
 */
abstract class StoreSeederUnitTestCase extends WP_UnitTestCase {

	use TestHelpers\Traits\Wp_Rest_Request_Trait;

	/**
	 * REST API server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected WP_REST_Server $server;

	/**
	 * The namespace of the plugin's REST routes.
	 *
	 * @var string
	 */
	protected string $namespace = 'storeseeder/v1';

	/**
	 * Whether the test is a pure unit test that needs neither REST nor a DB.
	 *
	 * @var bool
	 */
	protected bool $is_unit_test = false;

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Pin the target platform for the whole suite. With more than one shipped driver
		// active in the environment — and a dev machine will have several — `Auto` is
		// deliberately ambiguous, because guessing which store to write to is the one failure
		// nobody notices afterwards. A test about a controller or a generator has to state the
		// target, or it is testing resolution by accident and answering 409.
		//
		// Tests that *are* about resolution clear this in their own setUp; see ResolverTest.
		add_filter( 'storeseeder_target_platform', array( $this, 'pinned_test_platform' ) );

		if ( $this->is_unit_test ) {
			return;
		}

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		/**
		 * Fires when preparing to serve a REST API request.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_REST_Server $wp_rest_server Server object.
		 */
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Tear down the test case.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'storeseeder_target_platform', array( $this, 'pinned_test_platform' ) );
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * The platform the suite writes to unless a test says otherwise.
	 *
	 * Fluent Cart for preference, since it is the driver every resource has a writer for; any
	 * other active driver rather than nothing, so the suite still runs on a machine without it.
	 *
	 * @since 1.1.0
	 *
	 * @param string|null $requested What the caller asked for.
	 *
	 * @return string|null
	 */
	public function pinned_test_platform( $requested ) {
		// An explicit request always wins — this only fills in for `Auto`.
		if ( is_string( $requested ) && '' !== $requested && 'auto' !== $requested ) {
			return $requested;
		}

		$active = Registry::instance()->active();

		if ( array() === $active ) {
			return $requested;
		}

		return isset( $active['fluent-cart'] ) ? 'fluent-cart' : (string) array_key_first( $active );
	}

	/**
	 * Skip the test unless Fluent Cart is loaded in the environment.
	 *
	 * The generators create data through Fluent Cart models, so without the
	 * plugin there is nothing to exercise.
	 *
	 * @return void
	 */
	protected function require_fluent_cart(): void {
		$this->require_platform( 'fluent-cart' );
	}

	/**
	 * Skip unless one platform's driver can actually write.
	 *
	 * Driver tests need the platform itself on disk, and a contributor will rarely
	 * have all of them. Skipping is the honest outcome — a driver test that passes
	 * without its platform present is testing nothing.
	 *
	 * @param string $id Platform id, as the registry knows it.
	 *
	 * @return void
	 */
	protected function require_platform( string $id ): void {
		$platform = Registry::instance()->get( $id );

		if ( null === $platform || ! $platform->is_active() ) {
			$this->markTestSkipped(
				sprintf( '%s is not active in the test environment.', $id )
			);
		}
	}

	/**
	 * Create a WP_REST_Request for a plugin route.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route path, relative to the namespace.
	 *
	 * @return WP_REST_Request
	 */
	protected function get_wp_rest_request( string $method, string $route ): WP_REST_Request {
		return new WP_REST_Request( strtoupper( $method ), $this->get_route( $route ) );
	}

	/**
	 * Build a full route with the plugin namespace.
	 *
	 * @param string $route The route path.
	 *
	 * @return string
	 */
	protected function get_route( string $route ): string {
		return '/' . $this->namespace . $route;
	}

	/**
	 * Create a test user with administrator capabilities.
	 *
	 * @return int
	 */
	protected function create_admin_user(): int {
		return $this->factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Create a test user with customer capabilities.
	 *
	 * @return int
	 */
	protected function create_customer_user(): int {
		return $this->factory->user->create( array( 'role' => 'customer' ) );
	}

	/**
	 * Assert that an action hook has fired.
	 *
	 * @param string $action_name Action name.
	 *
	 * @return void
	 */
	protected function assertActionFired( string $action_name ): void {
		$this->assertTrue( did_action( $action_name ) > 0, "Action '{$action_name}' was not fired." );
	}
}
