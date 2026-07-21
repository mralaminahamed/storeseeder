<?php
/**
 * Tests for the sample-data REST surface and consent option.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

use StoreSeeder;
use WP_REST_Request;

/**
 * @covers \StoreSeeder
 */
class SampleDataConsentTest extends StoreSeederUnitTestCase {

	/**
	 * @var StoreSeeder
	 */
	private StoreSeeder $plugin;

	public function setUp(): void {
		parent::setUp();
		$this->plugin = storeseeder();
		// Force the dependency gate open so register_rest_routes() actually
		// registers (check_dependencies() reads option_active_plugins).
		add_filter(
			'option_active_plugins',
			static function ( $plugins ) {
				$plugins   = is_array( $plugins ) ? $plugins : array();
				$plugins[] = 'fluent-cart/fluent-cart.php';
				return $plugins;
			}
		);
		delete_option( 'storeseeder_sample_data_consent' );
		do_action( 'rest_api_init' );
	}

	public function tearDown(): void {
		delete_option( 'storeseeder_sample_data_consent' );
		unset( $this->plugin );
		parent::tearDown();
	}

	public function test_download_sample_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/storeseeder/v1/download-sample', $routes );
	}

	public function test_status_reports_shape(): void {
		$data = $this->plugin->rest_sample_data_status()->get_data();
		$this->assertArrayHasKey( 'exists', $data );
		$this->assertArrayHasKey( 'last_synced', $data );
		$this->assertArrayHasKey( 'repo_url', $data );
		$this->assertSame(
			'https://github.com/mralaminahamed/storeseeder-sample-data-fluent-cart',
			$data['repo_url']
		);
	}

	public function test_permission_check_denies_subscriber(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		$this->assertFalse( $this->plugin->rest_permission_check() );
	}

	public function test_permission_check_allows_admin(): void {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$this->assertTrue( $this->plugin->rest_permission_check() );
	}
}
