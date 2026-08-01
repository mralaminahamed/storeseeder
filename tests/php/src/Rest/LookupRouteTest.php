<?php
/**
 * Tests for the entity-lookup endpoint and the driver seam behind it.
 *
 * `customer_id` and `product_id` are foreign keys into the target store, and the admin rendered
 * them as a number box — answerable only by someone who already knew the id. This is what the
 * picker reads, so it is also a new place where one logged-in user could learn about another's
 * records: the gate matters as much as the results.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Rest;

use StoreSeeder\Platforms\Registry;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Tests\StoreSeederUnitTestCase;
use WP_REST_Request;

/**
 * @covers \StoreSeeder\Platforms\Platform_Driver::search
 */
class LookupRouteTest extends StoreSeederUnitTestCase {

	/**
	 * Dispatch a lookup request.
	 *
	 * @param array<string, mixed> $params Query parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function lookup( array $params ) {
		$request = new WP_REST_Request( 'GET', '/storeseeder/v1/lookup' );
		$request->set_query_params( $params );

		return rest_get_server()->dispatch( $request );
	}

	private function as_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	// -----------------------------------------------------------------------------------
	// The gate.
	// -----------------------------------------------------------------------------------

	public function test_it_is_closed_to_anonymous_callers(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->lookup( array( 'resource' => Resource::CUSTOMER ) )->get_status() );
	}

	/**
	 * A subscriber is exactly the person this must not answer: the results name customers and
	 * their email addresses.
	 */
	public function test_it_is_closed_to_a_role_without_the_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( 403, $this->lookup( array( 'resource' => Resource::CUSTOMER ) )->get_status() );
	}

	// -----------------------------------------------------------------------------------
	// Validation.
	// -----------------------------------------------------------------------------------

	/**
	 * WordPress does not apply schema validation to hand-written `args` on its own, so without an
	 * explicit validate_callback the enum is decoration and an unsupported resource answers 200
	 * with an empty list.
	 */
	public function test_an_unsupported_resource_is_refused(): void {
		$this->as_admin();

		$this->assertSame( 400, $this->lookup( array( 'resource' => Resource::ORDER ) )->get_status() );
	}

	public function test_a_missing_resource_is_refused(): void {
		$this->as_admin();

		$this->assertSame( 400, $this->lookup( array() )->get_status() );
	}

	public function test_a_limit_beyond_the_ceiling_is_refused(): void {
		$this->as_admin();

		$this->assertSame(
			400,
			$this->lookup(
				array(
					'resource' => Resource::CUSTOMER,
					'limit'    => 500,
				)
			)->get_status()
		);
	}

	public function test_an_unknown_platform_says_so_rather_than_guessing(): void {
		$this->as_admin();

		$response = $this->lookup(
			array(
				'resource' => Resource::CUSTOMER,
				'platform' => 'not-a-platform',
			)
		);

		$this->assertSame( 409, $response->get_status() );
	}

	// -----------------------------------------------------------------------------------
	// Results.
	// -----------------------------------------------------------------------------------

	public function test_it_finds_a_customer_by_name(): void {
		$this->require_platform( 'woocommerce' );
		$this->as_admin();

		self::factory()->user->create(
			array(
				'role'         => 'customer',
				'display_name' => 'Grace Hopper',
				'user_email'   => 'grace@example.test',
			)
		);

		$data = $this->lookup(
			array(
				'resource' => Resource::CUSTOMER,
				'platform' => 'woocommerce',
				'search'   => 'Hopper',
			)
		)->get_data();

		$labels = wp_list_pluck( $data['results'], 'label' );

		$this->assertNotEmpty( $data['results'] );
		$this->assertStringContainsString( 'Grace Hopper', implode( ' ', $labels ) );
		// The email carries the weight: two customers share a name far more often than an address.
		$this->assertStringContainsString( 'grace@example.test', implode( ' ', $labels ) );
	}

	/**
	 * Every result has to carry an id, because the id is the whole point — a label with nothing to
	 * send back is an option that cannot be chosen.
	 */
	public function test_every_result_carries_an_id_and_a_label(): void {
		$this->require_platform( 'woocommerce' );
		$this->as_admin();

		self::factory()->user->create( array( 'role' => 'customer' ) );

		foreach ( $this->lookup(
			array(
				'resource' => Resource::CUSTOMER,
				'platform' => 'woocommerce',
			)
		)->get_data()['results'] as $result ) {
			$this->assertArrayHasKey( 'id', $result );
			$this->assertArrayHasKey( 'label', $result );
			$this->assertIsInt( $result['id'] );
			$this->assertNotSame( '', $result['label'] );
		}
	}

	public function test_a_search_matching_nothing_is_an_empty_list_not_an_error(): void {
		$this->require_platform( 'woocommerce' );
		$this->as_admin();

		$response = $this->lookup(
			array(
				'resource' => Resource::CUSTOMER,
				'platform' => 'woocommerce',
				'search'   => 'zzz-nobody-is-called-this-zzz',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data()['results'] );
	}

	// -----------------------------------------------------------------------------------
	// The seam.
	// -----------------------------------------------------------------------------------

	/**
	 * A resource no driver searches is an empty list rather than a failure: the picker falls back
	 * to accepting a typed id, which is what it did before this existed.
	 */
	public function test_a_driver_returns_nothing_for_a_resource_it_does_not_search(): void {
		$this->require_platform( 'woocommerce' );

		$platform = Registry::instance()->get( 'woocommerce' );

		$this->assertSame( array(), $platform->search( Resource::COUPON ) );
	}

	public function test_the_filter_can_supply_results(): void {
		$this->require_platform( 'woocommerce' );

		add_filter(
			'storeseeder_platform_search_woocommerce',
			static function (): array {
				return array(
					array(
						'id'    => 7,
						'label' => 'From a filter',
					),
				);
			}
		);

		$results = Registry::instance()->get( 'woocommerce' )->search( Resource::COUPON );

		remove_all_filters( 'storeseeder_platform_search_woocommerce' );

		$this->assertSame( array( array( 'id' => 7, 'label' => 'From a filter' ) ), $results );
	}

	/**
	 * A filter returns whatever it likes, and an entry with no id would reach the admin as an
	 * option that cannot be chosen.
	 */
	public function test_malformed_filter_entries_are_dropped(): void {
		$this->require_platform( 'woocommerce' );

		add_filter(
			'storeseeder_platform_search_woocommerce',
			static function (): array {
				return array(
					array( 'label' => 'no id' ),
					array( 'id' => 3 ),
					'not an array',
					array(
						'id'    => 9,
						'label' => 'Kept',
					),
				);
			}
		);

		$results = Registry::instance()->get( 'woocommerce' )->search( Resource::PRODUCT );

		remove_all_filters( 'storeseeder_platform_search_woocommerce' );

		$this->assertSame( array( array( 'id' => 9, 'label' => 'Kept' ) ), $results );
	}
}
