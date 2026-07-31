<?php
/**
 * Tests for the /access routes.
 *
 * The write route is the one that matters: it changes who may write rows into the store,
 * so it is gated on manage_options rather than on the plugin's own capability. A route
 * that a granted role could POST to would let that role widen access further.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Rest;

use StoreSeeder\Access;
use StoreSeeder\Tests\StoreSeederUnitTestCase;
use WP_REST_Request;

/**
 * @covers \StoreSeeder::rest_access
 * @covers \StoreSeeder::rest_set_access
 * @covers \StoreSeeder::rest_manage_access_permission_check
 */
class AccessRouteTest extends StoreSeederUnitTestCase {

	public function tearDown(): void {
		delete_option( Access::ROLES_OPTION );
		remove_all_filters( 'storeseeder_capability' );
		parent::tearDown();
	}

	/**
	 * Dispatch against the plugin's namespace.
	 *
	 * @param string               $method HTTP method.
	 * @param array<string, mixed> $body   JSON body for a write.
	 *
	 * @return \WP_REST_Response
	 */
	private function request( string $method, array $body = array() ) {
		$request = new WP_REST_Request( $method, '/storeseeder/v1/access' );

		if ( array() !== $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	public function test_the_route_is_registered(): void {
		$this->assertArrayHasKey(
			'/storeseeder/v1/access',
			rest_get_server()->get_routes( 'storeseeder/v1' )
		);
	}

	public function test_reading_reports_the_roles_and_the_default_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$data = $this->request( 'GET' )->get_data();

		$this->assertSame( 'manage_options', $data['capability'] );
		$this->assertFalse( $data['filtered'] );
		$this->assertTrue( $data['canManage'] );
		$this->assertSame( array(), $data['allowedRoles'] );
		$this->assertArrayHasKey( 'editor', $data['roles'] );
		$this->assertArrayNotHasKey( Access::ADMIN_ROLE, $data['roles'] );
	}

	public function test_an_administrator_can_grant_a_role(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = $this->request( 'POST', array( 'roles' => array( 'editor' ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'editor' ), $response->get_data()['allowedRoles'] );
		$this->assertSame( array( 'editor' ), Access::allowed_roles() );
	}

	/**
	 * The self-escalation guard, end to end: a granted editor may read the setting but
	 * gets a 403 trying to change it.
	 */
	public function test_a_granted_role_can_read_but_not_write(): void {
		Access::set_allowed_roles( array( 'editor' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$read = $this->request( 'GET' );

		$this->assertSame( 200, $read->get_status() );
		$this->assertFalse( $read->get_data()['canManage'] );

		$write = $this->request( 'POST', array( 'roles' => array( 'editor', 'author' ) ) );

		$this->assertSame( 403, $write->get_status() );
		$this->assertSame( array( 'editor' ), Access::allowed_roles() );
	}

	public function test_a_user_with_no_access_cannot_even_read(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( 403, $this->request( 'GET' )->get_status() );
	}

	public function test_a_filtered_capability_is_reported_as_filtered(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter(
			'storeseeder_capability',
			static function (): string {
				return 'edit_shop_orders';
			}
		);

		$data = $this->request( 'GET' )->get_data();

		$this->assertSame( 'edit_shop_orders', $data['capability'] );
		$this->assertTrue( $data['filtered'] );
	}

	public function test_unknown_roles_are_rejected_rather_than_stored(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$data = $this->request( 'POST', array( 'roles' => array( 'editor', 'wizard' ) ) )->get_data();

		$this->assertSame( array( 'editor' ), $data['allowedRoles'] );
	}
}
