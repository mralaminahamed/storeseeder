<?php
/**
 * Tests for the /generated routes.
 *
 * The DELETE route removes rows from the live store, so what it must never do is act on
 * anything the ledger did not record — that is the difference between a cleanup and a
 * catastrophe on a staging site restored from production.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Rest;

use StoreSeeder\Access;
use StoreSeeder\Generation\Ledger;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Tests\StoreSeederUnitTestCase;
use WP_REST_Request;

/**
 * @covers \StoreSeeder::rest_generated
 * @covers \StoreSeeder::rest_delete_generated
 */
class GeneratedRouteTest extends StoreSeederUnitTestCase {

	public function setUp(): void {
		parent::setUp();
		Ledger::install();
		Ledger::forget_all();
	}

	public function tearDown(): void {
		Ledger::forget_all();
		delete_option( Access::ROLES_OPTION );
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
		$request = new WP_REST_Request( $method, '/storeseeder/v1/generated' );

		if ( array() !== $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	public function test_the_route_is_registered(): void {
		$this->assertArrayHasKey(
			'/storeseeder/v1/generated',
			rest_get_server()->get_routes( 'storeseeder/v1' )
		);
	}

	public function test_reading_reports_nothing_on_a_clean_site(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$data = $this->request( 'GET' )->get_data();

		$this->assertSame( 0, $data['total'] );
		$this->assertSame( array(), $data['resources'] );
		$this->assertSame( array(), $data['platforms'] );
	}

	public function test_reading_reports_the_ledger_grouped_by_resource(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		Ledger::record( 'fluent-cart', Resource::PRODUCT, 1 );
		Ledger::record( 'fluent-cart', Resource::PRODUCT, 2 );
		Ledger::record( 'fluent-cart', Resource::ORDER, 3 );

		$data = $this->request( 'GET' )->get_data();

		$this->assertSame( 3, $data['total'] );
		$this->assertCount( 2, $data['resources'] );
		$this->assertSame( array( 'fluent-cart' ), $data['platforms'] );

		$counts = wp_list_pluck( $data['resources'], 'count', 'resource' );
		$this->assertSame( 2, $counts[ Resource::PRODUCT ] );
		$this->assertSame( 1, $counts[ Resource::ORDER ] );
	}

	/**
	 * Reported in deletion order, so the list the admin shows and the work it describes
	 * cannot disagree — a transaction is listed above the order it belongs to.
	 */
	public function test_resources_are_listed_in_deletion_order(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		Ledger::record( 'fluent-cart', Resource::ORDER, 1 );
		Ledger::record( 'fluent-cart', Resource::TRANSACTION, 2 );

		$listed = wp_list_pluck( $this->request( 'GET' )->get_data()['resources'], 'resource' );

		$this->assertLessThan(
			array_search( Resource::ORDER, $listed, true ),
			array_search( Resource::TRANSACTION, $listed, true )
		);
	}

	/**
	 * Rows recorded against a platform with no active driver cannot be deleted, and the
	 * response says so rather than reporting a success that removed nothing.
	 */
	public function test_deleting_reports_what_it_could_not_do(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		Ledger::record( 'departed-cart', Resource::PRODUCT, 1 );

		$data = $this->request( 'DELETE' )->get_data();

		$this->assertSame( 0, $data['deleted'] );
		$this->assertSame( 1, $data['remaining'] );
		$this->assertNotEmpty( $data['errors'] );
		// The record survives: it is the only remaining trace of the row.
		$this->assertSame( 1, Ledger::total() );
	}

	/**
	 * The escape hatch for a ledger that no longer matches reality. It must be explicit —
	 * a plain DELETE has to try to delete, not quietly drop the records.
	 */
	public function test_forget_drops_the_records_and_leaves_the_store_alone(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		Ledger::record( 'departed-cart', Resource::PRODUCT, 1 );
		Ledger::record( 'departed-cart', Resource::ORDER, 2 );

		$data = $this->request( 'DELETE', array( 'forget' => true ) )->get_data();

		$this->assertSame( 2, $data['forgotten'] );
		$this->assertSame( 0, $data['deleted'] );
		$this->assertSame( 0, Ledger::total() );
	}

	public function test_an_unknown_resource_deletes_nothing(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		Ledger::record( 'fluent-cart', Resource::PRODUCT, 1 );

		$data = $this->request( 'DELETE', array( 'resource' => 'widgets' ) )->get_data();

		$this->assertSame( 0, $data['deleted'] );
		$this->assertSame( 1, Ledger::total() );
	}

	/**
	 * Gated the same way as generation: it removes exactly the rows that gate allowed the
	 * user to create, and nothing else.
	 */
	public function test_a_granted_role_may_read_and_delete(): void {
		Access::set_allowed_roles( array( 'editor' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 200, $this->request( 'GET' )->get_status() );
		$this->assertSame( 200, $this->request( 'DELETE' )->get_status() );
	}

	public function test_a_user_with_no_access_cannot_read_or_delete(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( 403, $this->request( 'GET' )->get_status() );
		$this->assertSame( 403, $this->request( 'DELETE' )->get_status() );
	}

	public function test_the_limit_is_capped(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 400, $this->request( 'DELETE', array( 'limit' => 5000 ) )->get_status() );
	}
}
