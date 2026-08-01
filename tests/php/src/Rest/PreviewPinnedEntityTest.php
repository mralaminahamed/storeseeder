<?php
/**
 * Tests that a preview shows the record a run is pinned to.
 *
 * A preview row comes from FakerPHP, because it runs on every keystroke and may not touch the
 * database. So a run pinned to one customer previewed three different names — which says the
 * opposite of what the run will do, on the one screen whose whole job is showing what the run will
 * do.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Rest;

use StoreSeeder\Tests\StoreSeederUnitTestCase;
use WP_REST_Request;

/**
 * @covers \StoreSeeder\Rest\Controller::name_pinned_entities
 */
class PreviewPinnedEntityTest extends StoreSeederUnitTestCase {

	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Preview an order run.
	 *
	 * @param array<string, mixed> $body Request body.
	 *
	 * @return array<string, mixed>
	 */
	private function preview( array $body ): array {
		$request = new WP_REST_Request( 'POST', '/storeseeder/v1/orders/preview' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		return (array) rest_get_server()->dispatch( $request )->get_data();
	}

	/**
	 * @param array<string, mixed> $preview A preview response.
	 *
	 * @return array<int, string>
	 */
	private function customers( array $preview ): array {
		$names = array();

		foreach ( (array) ( $preview['rows'] ?? array() ) as $row ) {
			$names[] = (string) ( $row['customer']['v'] ?? '' );
		}

		return $names;
	}

	public function test_a_pinned_customer_is_the_only_one_previewed(): void {
		$this->require_platform( 'woocommerce' );

		$id = self::factory()->user->create(
			array(
				'role'         => 'customer',
				'display_name' => 'Ada Lovelace',
				'user_email'   => 'ada@example.test',
			)
		);

		$names = $this->customers(
			$this->preview(
				array(
					'count'       => 4,
					'platform'    => 'woocommerce',
					'customer_id' => $id,
				)
			)
		);

		$this->assertNotEmpty( $names );
		$this->assertCount( 1, array_unique( $names ) );
		$this->assertStringContainsString( 'Ada Lovelace', $names[0] );
	}

	/**
	 * Without a pin the spread is the point: the preview is showing that orders land on different
	 * customers.
	 */
	public function test_an_unpinned_run_still_previews_a_spread(): void {
		$this->require_platform( 'woocommerce' );

		$names = $this->customers(
			$this->preview(
				array(
					'count'    => 6,
					'platform' => 'woocommerce',
				)
			)
		);

		$this->assertNotEmpty( $names );
		$this->assertGreaterThan( 1, count( array_unique( $names ) ) );
	}

	/**
	 * An id belonging to nobody leaves the generated name in place. Blanking the column would make
	 * a typo look like a broken preview.
	 */
	public function test_an_unknown_id_leaves_the_preview_alone(): void {
		$this->require_platform( 'woocommerce' );

		$names = $this->customers(
			$this->preview(
				array(
					'count'       => 3,
					'platform'    => 'woocommerce',
					'customer_id' => 999999,
				)
			)
		);

		foreach ( $names as $name ) {
			$this->assertNotSame( '', $name );
		}
	}

	/**
	 * The substitution is keyed on a column the generator actually has, so a resource without one
	 * is untouched rather than erroring.
	 */
	public function test_a_resource_without_the_column_is_untouched(): void {
		$this->require_platform( 'woocommerce' );

		$request = new WP_REST_Request( 'POST', '/storeseeder/v1/coupons/preview' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'count'       => 2,
					'platform'    => 'woocommerce',
					'customer_id' => 1,
				)
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $response->get_data()['rows'] );
	}
}
