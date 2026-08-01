<?php
/**
 * Tests for the admin-screen seam.
 *
 * A recipe finishes by saying it made 180 products, and the next thing a person wants is to look at
 * them. Only the driver can say where they are — WooCommerce moved orders off `edit.php` under
 * HPOS, Fluent Cart uses hash routes — so this is about the seam holding its shape rather than
 * about any one URL.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests\Platform;

use StoreSeeder\Platforms\Platform_Driver;
use StoreSeeder\Platforms\Registry;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Tests\StoreSeederUnitTestCase;

/**
 * @covers \StoreSeeder\Platforms\Platform_Driver::admin_url
 */
class AdminUrlTest extends StoreSeederUnitTestCase {

	public function tear_down() {
		remove_all_filters( 'storeseeder_platform_admin_url_woocommerce' );

		parent::tear_down();
	}

	private function woocommerce(): Platform_Driver {
		$platform = Registry::instance()->get( 'woocommerce' );

		if ( ! $platform instanceof Platform_Driver ) {
			$this->markTestSkipped( 'WooCommerce driver not available.' );
		}

		return $platform;
	}

	public function test_a_resource_with_a_screen_gets_an_absolute_url(): void {
		$url = $this->woocommerce()->admin_url( Resource::PRODUCT );

		$this->assertIsString( $url );
		// Absolute, through admin_url(), so a site in a subdirectory still gets a working link.
		$this->assertStringStartsWith( admin_url(), $url );
		$this->assertStringContainsString( 'post_type=product', $url );
	}

	/**
	 * Variations have no list of their own. A link to the parent screen would go somewhere other
	 * than where it says, which is worse than plain text.
	 */
	public function test_a_resource_with_no_screen_returns_null(): void {
		$this->assertNull( $this->woocommerce()->admin_url( Resource::PRODUCT_VARIATION ) );
		$this->assertNull( $this->woocommerce()->admin_url( 'not_a_resource' ) );
	}

	/**
	 * The whole reason this lives on the driver: orders are on a different screen depending on a
	 * setting, and no rule about resource names could know that.
	 */
	public function test_orders_follow_the_stores_own_order_storage(): void {
		$url = $this->woocommerce()->admin_url( Resource::ORDER );

		$this->assertIsString( $url );

		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		$this->assertStringContainsString(
			$hpos ? 'page=wc-orders' : 'post_type=shop_order',
			$url
		);
	}

	public function test_a_filter_can_move_a_screen(): void {
		add_filter(
			'storeseeder_platform_admin_url_woocommerce',
			static function ( $path, $resource_type ) {
				return Resource::PRODUCT === $resource_type ? 'edit.php?post_type=my_product' : $path;
			},
			10,
			2
		);

		$this->assertStringContainsString(
			'post_type=my_product',
			(string) $this->woocommerce()->admin_url( Resource::PRODUCT )
		);
	}

	/**
	 * A filter that returns something unusable falls back to no link rather than to a broken one —
	 * `admin_url( '' )` would point at the dashboard and look deliberate.
	 */
	public function test_a_filter_returning_nothing_usable_yields_no_link(): void {
		foreach ( array( '', null, false, 42, array() ) as $bad ) {
			remove_all_filters( 'storeseeder_platform_admin_url_woocommerce' );

			add_filter(
				'storeseeder_platform_admin_url_woocommerce',
				static function () use ( $bad ) {
					return $bad;
				}
			);

			$this->assertNull(
				$this->woocommerce()->admin_url( Resource::PRODUCT ),
				(string) wp_json_encode( $bad )
			);
		}
	}

	/**
	 * The default is null, not a fatal: a third-party driver written before this existed keeps
	 * loading and simply offers no links.
	 */
	public function test_a_driver_that_does_not_implement_it_answers_null(): void {
		$driver = new class() extends Platform_Driver {
			public function id(): string {
				return 'stub-no-screens';
			}

			public function label(): string {
				return 'Stub';
			}

			public function is_active(): bool {
				return true;
			}

			public function version(): ?string {
				return null;
			}

			protected function writer_classes(): array {
				return array();
			}

			protected function capabilities(): array {
				return array();
			}
		};

		$this->assertNull( $driver->admin_url( Resource::PRODUCT ) );
	}
}
