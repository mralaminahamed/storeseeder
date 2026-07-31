<?php
/**
 * Tests for the /mcp routes.
 *
 * Reading is behind the plugin's own gate; writing is behind manage_options, because these
 * switches decide what an AI agent may do to the store. A route a granted role could POST
 * to would let that role hand itself a tool the administrator had withdrawn.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Rest;

use StoreSeeder\Access;
use StoreSeeder\MCP\Registry;
use StoreSeeder\MCP\Settings;
use StoreSeeder\Tests\StoreSeederUnitTestCase;
use WP_REST_Request;

/**
 * @covers \StoreSeeder::rest_mcp
 * @covers \StoreSeeder::rest_set_mcp
 */
class McpRouteTest extends StoreSeederUnitTestCase {

	public function tearDown(): void {
		delete_option( Access::ROLES_OPTION );

		foreach ( array( Settings::ENABLED_OPTION, Settings::PREVIEW_OPTION, Settings::GENERATE_OPTION ) as $option ) {
			delete_option( $option );
		}

		Registry::reset();
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
		$request = new WP_REST_Request( $method, '/storeseeder/v1/mcp' );

		if ( array() !== $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	public function test_the_route_is_registered(): void {
		$this->assertArrayHasKey(
			'/storeseeder/v1/mcp',
			rest_get_server()->get_routes( 'storeseeder/v1' )
		);
	}

	public function test_reading_reports_the_toggles_and_what_they_expose(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$data = $this->request( 'GET' )->get_data();

		foreach ( array( 'enabled', 'preview', 'generate', 'tools', 'abilities', 'can_manage', 'route' ) as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}

		$this->assertTrue( $data['can_manage'] );
		$this->assertSame( 2 * $data['abilities'], $data['tools'] );
	}

	public function test_an_administrator_can_withdraw_generation(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = $this->request( 'POST', array( 'generate' => false ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['generate'] );
		$this->assertFalse( Settings::generate_enabled() );
		// Preview survives, which is the point of the switch.
		$this->assertTrue( Settings::preview_enabled() );
	}

	/**
	 * The reason the endpoint takes a partial body: two administrators changing different
	 * switches must not undo each other, and the admin sends only the one that moved.
	 */
	public function test_an_absent_toggle_is_left_alone(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->request( 'POST', array( 'generate' => false ) );
		$data = $this->request( 'POST', array( 'preview' => false ) )->get_data();

		$this->assertFalse( $data['generate'] );
		$this->assertFalse( $data['preview'] );
		$this->assertTrue( $data['enabled'] );
		$this->assertSame( 0, $data['tools'] );
	}

	public function test_a_granted_role_can_read_but_not_write(): void {
		Access::set_allowed_roles( array( 'editor' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$read = $this->request( 'GET' );

		$this->assertSame( 200, $read->get_status() );
		$this->assertFalse( $read->get_data()['can_manage'] );

		$write = $this->request( 'POST', array( 'generate' => false ) );

		$this->assertSame( 403, $write->get_status() );
		$this->assertTrue( Settings::generate_enabled() );
	}

	public function test_a_user_with_no_access_cannot_even_read(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( 403, $this->request( 'GET' )->get_status() );
	}
}
