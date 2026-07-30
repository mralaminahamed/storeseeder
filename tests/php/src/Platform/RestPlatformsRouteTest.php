<?php
/**
 * Tests for the platform REST surface.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Platform;

use StoreSeeder;
use StoreSeeder\Platform\Registry;
use StoreSeeder\Platform\Resolver;
use StoreSeeder\Tests\StoreSeederUnitTestCase;
use WP_REST_Request;

/**
 * @covers \StoreSeeder
 */
class RestPlatformsRouteTest extends StoreSeederUnitTestCase {

	/**
	 * @var StoreSeeder
	 */
	private StoreSeeder $plugin;

	public function setUp(): void {
		parent::setUp();
		Registry::reset();
		delete_option( Resolver::OPTION );
		$this->plugin = storeseeder();
		do_action( 'rest_api_init' );
	}

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_platforms' );
		delete_option( Resolver::OPTION );
		Registry::reset();
		unset( $this->plugin );
		parent::tearDown();
	}

	public function test_routes_are_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/storeseeder/v1/platforms', $routes );
		$this->assertArrayHasKey( '/storeseeder/v1/platforms/target', $routes );
	}

	public function test_payload_shape(): void {
		$data = $this->plugin->rest_platforms()->get_data();

		$this->assertArrayHasKey( 'platforms', $data );
		$this->assertArrayHasKey( 'stored', $data );
		$this->assertArrayHasKey( 'resolved', $data );
		$this->assertArrayHasKey( 'ambiguous', $data );

		$first = $data['platforms'][0];
		$this->assertArrayHasKey( 'id', $first );
		$this->assertArrayHasKey( 'label', $first );
		$this->assertArrayHasKey( 'active', $first );
		$this->assertArrayHasKey( 'version', $first );
		$this->assertArrayHasKey( 'supports', $first );
	}

	/**
	 * The client dims a generator and explains why, so each capability has to arrive
	 * as a verdict plus a reason, not a bare boolean.
	 */
	public function test_capabilities_arrive_as_verdicts(): void {
		$data     = $this->plugin->rest_platforms()->get_data();
		$supports = $data['platforms'][0]['supports'];

		$this->assertArrayHasKey( 'product', $supports );
		$this->assertArrayHasKey( 'supported', $supports['product'] );
		$this->assertArrayHasKey( 'reason', $supports['product'] );
		$this->assertArrayHasKey( 'extension', $supports['product'] );
	}

	public function test_resolved_is_null_when_the_target_is_ambiguous(): void {
		add_filter(
			'storeseeder_platforms',
			static function ( array $platforms ): array {
				$platforms[] = new StubPlatform( 'stub-cart', true );
				return $platforms;
			}
		);
		Registry::reset();

		$data = $this->plugin->rest_platforms()->get_data();

		$this->assertNull( $data['resolved'] );
		$this->assertTrue( $data['ambiguous'] );
	}

	public function test_setting_the_target_persists_and_echoes_state(): void {
		$request = new WP_REST_Request( 'POST', '/storeseeder/v1/platforms/target' );
		$request->set_param( 'platform', 'fluent-cart' );

		$response = $this->plugin->rest_set_target_platform( $request );

		$this->assertNotWPError( $response );

		$data = $response->get_data();
		$this->assertSame( 'fluent-cart', $data['stored'] );
		$this->assertSame( 'fluent-cart', $data['resolved'] );
		$this->assertSame( 'fluent-cart', get_option( Resolver::OPTION ) );
	}

	public function test_setting_an_unknown_target_is_rejected(): void {
		$request = new WP_REST_Request( 'POST', '/storeseeder/v1/platforms/target' );
		$request->set_param( 'platform', 'not-a-cart' );

		$result = $this->plugin->rest_set_target_platform( $request );

		$this->assertWPError( $result );
		$this->assertSame( 'storeseeder_unknown_platform', $result->get_error_code() );
		$this->assertFalse( (bool) get_option( Resolver::OPTION ) );
	}
}
