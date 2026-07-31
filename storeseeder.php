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
 * Plugin URI:        https://github.com/mralaminahamed/storeseeder
 * Description:       Realistic test data for WordPress e-commerce platforms. Eighteen generators — products, customers, orders, coupons and more — write through a platform driver, so the same data can seed any supported store. Fluent Cart ships; other platforms can register their own driver. For development, testing, and demos.
 * Version:           1.0.0
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

define( 'STORESEEDER_VERSION', '1.0.0' );
define( 'STORESEEDER_PLUGIN_FILE', __FILE__ );
define( 'STORESEEDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STORESEEDER_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Load Composer autoloader.
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
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