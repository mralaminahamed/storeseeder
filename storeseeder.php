<?php
/**
 * StoreSeeder
 *
 * @package           StoreSeeder
 * @author            Al Amin Ahamed
 * @copyright         2025 Al Amin Ahamed
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       StoreSeeder
 * Plugin URI:        https://mralaminahamed.github.io/storeseeder
 * Description:       Realistic test data for WordPress e-commerce platforms. Twenty-one generators — products, customers, orders, coupons and more — write through a platform driver, so the same data can seed any supported store. Fluent Cart and WooCommerce ship; other platforms can register their own driver. For development, testing, and demos.
 * Version:           1.2.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Al Amin Ahamed
 * Author URI:        https://github.com/mralaminahamed/
 * Text Domain:       storeseeder
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STORESEEDER_VERSION', '1.2.0' );
define( 'STORESEEDER_PLUGIN_FILE', __FILE__ );
define( 'STORESEEDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STORESEEDER_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Tell WooCommerce this plugin is safe with High-Performance Order Storage.
 *
 * It always was: orders are written through `new WC_Order()` and `save()`, read
 * through `wc_get_orders()`, and the admin link branches on
 * `OrderUtil::custom_orders_table_usage_is_enabled()`. What was missing was
 * saying so — and WooCommerce treats a plugin that never declares as
 * incompatible, so every HPOS store was shown a warning about a plugin that had
 * nothing wrong with it.
 *
 * Registered here rather than inside the main class so it still runs when the
 * autoloader is absent and the class cannot load. A dead plugin is compatible
 * with everything, and it must not add a scary warning to its own silence.
 *
 * @since 1.2.1
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
);

// Load Composer autoloader.
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	/*
	 * Left silent, this plugin activated and then did nothing at all: no warning,
	 * no notice, and every menu it should add simply absent. Say what is wrong.
	 */
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'StoreSeeder is not running.', 'storeseeder' ),
				esc_html__( 'Its autoloader is missing. Run "composer install --no-dev" in the plugin directory, or install the packaged build from the release ZIP.', 'storeseeder' )
			);
		}
	);

	return;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Get main plugin instance
 *
 * @since 1.0.0
 * @return StoreSeeder Plugin instance.
 */
function storeseeder(): StoreSeeder {
	return StoreSeeder::get_instance();
}


storeseeder()->init();
