<?php
/**
 * Fluent Cart FakerPress
 *
 * @package           FluentCartFakerPress
 * @author            Al Amin Ahamed
 * @copyright         2025 Al Amin Ahamed
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Fluent Cart FakerPress
 * Plugin URI:        https://github.com/mralaminahamed/fluent-cart-fakerpress
 * Description:       Create realistic test data for your Fluent Cart store in seconds! Generate products, customers, orders, coupons and more with our intuitive admin interface. Perfect for development, testing, and demos. Features smart defaults, real-time validation, and seamless WordPress integration.
 * Version:           2.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Al Amin Ahamed
 * Author URI:        https://github.com/mralaminahamed/
 * Text Domain:       fluent-cart-fakerpress
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * Requires Plugins:  fluent-cart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLUENT_CART_FAKERPRESS_VERSION', '2.0.0' );
define( 'FLUENT_CART_FAKERPRESS_PLUGIN_FILE', __FILE__ );
define( 'FLUENT_CART_FAKERPRESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FLUENT_CART_FAKERPRESS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Load Composer autoloader.
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Get main plugin instance
 *
 * @since 1.0.0
 * @return FluentCart_FakerPress Plugin instance.
 */
function fluent_cart_fakerpress(): FluentCart_FakerPress {
	return FluentCart_FakerPress::get_instance();
}


fluent_cart_fakerpress()->init();