<?php
/**
 * Tests for the bootstrap's WooCommerce compatibility declaration.
 *
 * @package StoreSeeder\Tests
 */

namespace StoreSeeder\Tests;

/**
 * @covers ::storeseeder
 */
class BootstrapCompatibilityTest extends StoreSeederUnitTestCase {

	/**
	 * WooCommerce treats a plugin that never declares as incompatible with
	 * High-Performance Order Storage, so leaving this out put a warning about
	 * StoreSeeder on every HPOS store — while the plugin was in fact HPOS-safe
	 * throughout: orders are written with `new WC_Order()` and `save()`, read
	 * with `wc_get_orders()`, and the admin link already branches on
	 * `OrderUtil::custom_orders_table_usage_is_enabled()`.
	 *
	 * @return void
	 */
	public function test_declares_hpos_compatibility(): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			$this->markTestSkipped( 'WooCommerce is not loaded in this run.' );
		}

		$declared = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_features_for_plugin(
			plugin_basename( STORESEEDER_PLUGIN_FILE )
		);

		$this->assertContains( 'custom_order_tables', $declared['compatible'] );
	}

	/**
	 * The declaration is registered from the plugin file itself rather than from
	 * the main class, so that it still happens when the autoloader is missing and
	 * the class cannot load. A plugin that is not running is compatible with
	 * everything, and must not add a warning to its own silence.
	 *
	 * @return void
	 */
	public function test_the_declaration_does_not_depend_on_the_autoloader(): void {
		$source = file_get_contents( STORESEEDER_PLUGIN_FILE );

		$declaration = strpos( $source, 'before_woocommerce_init' );
		$guard       = strpos( $source, "file_exists( __DIR__ . '/vendor/autoload.php' )" );

		$this->assertNotFalse( $declaration );
		$this->assertNotFalse( $guard );
		$this->assertLessThan(
			$guard,
			$declaration,
			'The compatibility declaration must be registered before the autoloader guard can return.'
		);
	}

	/**
	 * A missing autoloader used to be completely silent: the plugin activated,
	 * did nothing, and said nothing about it.
	 *
	 * @return void
	 */
	public function test_a_missing_autoloader_would_warn_the_admin(): void {
		$source = file_get_contents( STORESEEDER_PLUGIN_FILE );

		$guard = strpos( $source, "file_exists( __DIR__ . '/vendor/autoload.php' )" );
		$notice = strpos( $source, 'StoreSeeder is not running.' );

		$this->assertNotFalse( $notice, 'The bail-out path must explain itself.' );
		$this->assertGreaterThan( $guard, $notice );
	}
}
