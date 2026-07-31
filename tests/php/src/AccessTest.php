<?php
/**
 * Tests for the capability gate.
 *
 * The gate is what stands between a subscriber and a route that writes rows into the
 * store's tables, so the fallback behaviour matters as much as the happy path: a filter
 * returning something unusable must not end up widening access.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

use StoreSeeder\Access;
use StoreSeeder\MCP\Ability;
use StoreSeeder\Rest\Registry as Rest_Registry;

/**
 * @covers \StoreSeeder\Access
 */
class AccessTest extends StoreSeederUnitTestCase {

	public function tearDown(): void {
		remove_all_filters( 'storeseeder_capability' );
		parent::tearDown();
	}

	public function test_default_capability(): void {
		$this->assertSame( 'manage_options', Access::capability() );
		$this->assertSame( Access::DEFAULT_CAPABILITY, Access::capability() );
	}

	public function test_filter_can_widen_the_gate(): void {
		add_filter(
			'storeseeder_capability',
			static function (): string {
				return 'edit_shop_orders';
			}
		);

		$this->assertSame( 'edit_shop_orders', Access::capability() );
	}

	/**
	 * current_user_can() with an empty or non-string capability is unpredictable —
	 * granted for a super admin, denied for everyone else — so an unusable filter
	 * return falls back rather than being passed through.
	 */
	public function test_unusable_filter_return_falls_back(): void {
		foreach ( array( '', null, false, array( 'manage_options' ), 42 ) as $bad ) {
			remove_all_filters( 'storeseeder_capability' );

			add_filter(
				'storeseeder_capability',
				static function () use ( $bad ) {
					return $bad;
				}
			);

			$this->assertSame( 'manage_options', Access::capability() );
		}
	}

	public function test_current_user_can_follows_the_filter(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertFalse( Access::current_user_can() );

		// `edit_pages` is an editor capability, so the same user now passes.
		add_filter(
			'storeseeder_capability',
			static function (): string {
				return 'edit_pages';
			}
		);

		$this->assertTrue( Access::current_user_can() );
	}

	/**
	 * The point of one gate rather than four: widening it must reach the REST routes
	 * and the MCP abilities, not only the admin menu.
	 */
	public function test_rest_and_mcp_share_the_gate(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$controller_class = Rest_Registry::instance()->all()['products'];
		$controller       = new $controller_class();
		$request          = new \WP_REST_Request( 'POST', '/storeseeder/v1/products/generate' );

		$this->assertInstanceOf(
			'WP_Error',
			$controller->generate_items_permissions_check( $request )
		);
		$this->assertFalse( Ability::permission_callback() );

		add_filter(
			'storeseeder_capability',
			static function (): string {
				return 'edit_pages';
			}
		);

		$this->assertTrue( $controller->generate_items_permissions_check( $request ) );
		$this->assertTrue( Ability::permission_callback() );
	}
}
