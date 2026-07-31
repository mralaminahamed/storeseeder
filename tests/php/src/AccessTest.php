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
		delete_option( Access::ROLES_OPTION );
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

	public function test_no_roles_are_allowed_by_default(): void {
		$this->assertSame( array(), Access::allowed_roles() );
	}

	public function test_administrator_is_never_an_assignable_role(): void {
		$roles = Access::assignable_roles();

		$this->assertArrayNotHasKey( Access::ADMIN_ROLE, $roles );
		$this->assertArrayHasKey( 'editor', $roles );
		$this->assertNotSame( '', $roles['editor'] );
	}

	/**
	 * An allowed role grants access without touching the capability, which is the point:
	 * a site owner reasons about "editors may generate data", not about edit_others_posts.
	 */
	public function test_an_allowed_role_grants_access(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertFalse( Access::current_user_can() );

		Access::set_allowed_roles( array( 'editor' ) );

		$this->assertTrue( Access::current_user_can() );
	}

	public function test_a_role_that_was_not_allowed_gains_nothing(): void {
		Access::set_allowed_roles( array( 'editor' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );

		$this->assertFalse( Access::current_user_can() );
	}

	public function test_logged_out_visitors_are_never_allowed(): void {
		Access::set_allowed_roles( array( 'editor' ) );
		wp_set_current_user( 0 );

		$this->assertFalse( Access::current_user_can() );
	}

	/**
	 * Only what the site defines is stored — a role deleted after being granted, or a
	 * slug that was never a role, must not sit in the option granting nothing visible.
	 */
	public function test_unknown_roles_are_discarded_on_save(): void {
		$stored = Access::set_allowed_roles( array( 'editor', 'not-a-role', 'editor' ) );

		$this->assertSame( array( 'editor' ), $stored );
		$this->assertSame( array( 'editor' ), Access::allowed_roles() );
	}

	/**
	 * The administrator role is not storable, so it cannot be revoked either — an
	 * administrator who could clear their own access would be one click from a site
	 * where nobody can reach the plugin or undo the setting.
	 */
	public function test_the_administrator_role_cannot_be_stored(): void {
		$this->assertSame( array(), Access::set_allowed_roles( array( Access::ADMIN_ROLE ) ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue( Access::current_user_can() );
	}

	/**
	 * The setting must not be self-escalating: a role granted access must not be able to
	 * grant access to more roles.
	 */
	public function test_only_administrators_may_change_who_has_access(): void {
		Access::set_allowed_roles( array( 'editor' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertTrue( Access::current_user_can() );
		$this->assertFalse( Access::current_user_can_manage() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue( Access::current_user_can_manage() );
	}

	/**
	 * Widening the capability by filter must not also hand over the ability to grant
	 * roles — current_user_can_manage() deliberately checks the default, not the filtered
	 * capability.
	 */
	public function test_the_capability_filter_does_not_confer_management(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		add_filter(
			'storeseeder_capability',
			static function (): string {
				return 'edit_pages';
			}
		);

		$this->assertTrue( Access::current_user_can() );
		$this->assertFalse( Access::current_user_can_manage() );
	}
}
