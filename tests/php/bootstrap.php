<?php
/**
 * PHPUnit bootstrap file for StoreSeeder
 */

// Define plugin directories..
define( 'TEST_STORESEEDER_DIR', dirname( __DIR__, 2 ) );
define( 'TEST_PLUGINS_DIR', dirname( __DIR__, 3 ) );
define( 'TEST_FC_DIR', TEST_PLUGINS_DIR . '/fluent-cart' );

/**
 * Platforms the suite knows how to load.
 *
 * Keyed by platform id so a driver test can ask for one by the same name the registry
 * uses. Each entry names the plugin directory (its main file is assumed to share the
 * name) and the driver namespace segment under StoreSeeder\Platforms.
 *
 * A platform is only loaded when StoreSeeder actually ships a driver for it. That is
 * not caution for its own sake: booting a plugin from a test bootstrap means meeting
 * whatever its main file expects, and EasyCommerce for one fatals with
 * "Class EasyCommerce\Bootstrap\Activator not found" because its own autoloader has
 * not run yet. A platform with no driver contributes nothing to the suite, so loading
 * it is pure risk -- and by the time a driver lands, whoever writes it is the right
 * person to work out how that plugin wants to be booted.
 *
 * @var array<string, array{dir: string, driver: string}>
 */
const TEST_PLATFORMS = array(
	'fluent-cart'  => array(
		'dir'    => 'fluent-cart',
		'driver' => 'Fluent_Cart',
	),
	'woocommerce'  => array(
		'dir'    => 'woocommerce',
		'driver' => 'Woo_Commerce',
	),
	'storeengine'  => array(
		'dir'    => 'storeengine',
		'driver' => 'Store_Engine',
	),
	'easycommerce' => array(
		'dir'    => 'easycommerce',
		'driver' => 'Easy_Commerce',
	),
);

/**
 * Whether a platform's plugin is on disk next to this checkout.
 *
 * @param string $id Platform id.
 *
 * @return bool
 */
function storeseeder_test_platform_available( string $id ): bool {
	$spec = TEST_PLATFORMS[ $id ] ?? null;

	if ( null === $spec ) {
		return false;
	}

	if ( ! class_exists( 'StoreSeeder\\Platforms\\Drivers\\' . $spec['driver'] . '\\Platform' ) ) {
		return false;
	}

	return file_exists( TEST_PLUGINS_DIR . '/' . $spec['dir'] . '/' . $spec['dir'] . '.php' );
}

// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available.
require_once TEST_STORESEEDER_DIR . '/vendor/autoload.php';

// Define WordPress test environment path
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: getenv( 'WP_PHPUNIT__DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

/**
 * Truncate StoreSeeder tables for clean test runs
 */
function storeseeder_truncate_table_data(): void {
	$tables = array(
		// The ledger of generated rows. Named `storeseeder_generated_data` here until the
		// table existed at all, which is why the truncate had been a no-op.
		'storeseeder_generated',
		// Add other tables as needed.
	);

	global $wpdb;
	foreach ( $tables as $table_name ) {
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table_name ) );
		if ( $table_exists ) {
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}{$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugins being tested
 */
function _manually_load_plugin() {
	// Load whichever platforms are present. Requiring one unconditionally is what
	// made the suite unrunnable without Fluent Cart specifically.
	foreach ( TEST_PLATFORMS as $id => $spec ) {
		if ( storeseeder_test_platform_available( $id ) ) {
			require TEST_PLUGINS_DIR . '/' . $spec['dir'] . '/' . $spec['dir'] . '.php';
		}
	}

	// Load our plugin.
	require TEST_STORESEEDER_DIR . '/storeseeder.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Install Fluent Cart for testing
 */
function install_fluent_cart() {
	// Skip if Fluent Cart doesn't exist..
	if ( ! storeseeder_test_platform_available( 'fluent-cart' ) ) {
		echo 'Warning: Fluent Cart plugin not found. Some tests may fail.' . PHP_EOL;
		return;
	}

	echo 'Installing Fluent Cart...' . PHP_EOL;

	/*
	 * Create Fluent Cart's tables. This has to happen on setup_theme, before
	 * Fluent Cart's own modules query them on init — its Tax module reads
	 * fct_meta there and takes the whole suite down if the schema is missing.
	 *
	 * DBMigrator is the current entry point. fluent_cart_install() was the old
	 * one and no longer exists in shipping releases, so it is only a fallback.
	 */
	if ( class_exists( '\FluentCart\Database\DBMigrator' ) ) {
		\FluentCart\Database\DBMigrator::migrateUp();
	} elseif ( function_exists( '\FluentCart\fluent_cart_install' ) ) {
		\FluentCart\fluent_cart_install();
	} else {
		echo 'Warning: no Fluent Cart installer found; tests touching Fluent Cart tables will fail.' . PHP_EOL;
		return;
	}

	// Reload capabilities after install.
	if ( version_compare( $GLOBALS['wp_version'], '4.7', '<' ) ) {
		$GLOBALS['wp_roles']->reinit();
	} else {
		$GLOBALS['wp_roles'] = null;
		wp_roles();
	}
}

/**
 * Create WooCommerce's tables.
 *
 * Runs on `setup_theme`, before WooCommerce's own modules query their schema on `init`.
 *
 * This is not optional, and deferring it to the tests that need it does not work: the plugin is
 * *loaded* for the whole suite — `_manually_load_plugin()` has to, or the driver reports itself
 * inactive — and a loaded WooCommerce hooks every post and user write the other four hundred
 * tests perform. With no tables behind it, those hooks and Action Scheduler query things that do
 * not exist, and the suite grinds to a halt rather than failing. Present and installed is the
 * only state that works.
 *
 * @return bool Whether the tables are available.
 */
function storeseeder_install_woocommerce_tables(): bool {
	static $installed = null;

	if ( null !== $installed ) {
		return $installed;
	}

	if ( ! storeseeder_test_platform_available( 'woocommerce' ) ) {
		echo 'Warning: WooCommerce plugin not found. WooCommerce driver tests will skip.' . PHP_EOL;
		$installed = false;

		return $installed;
	}

	if ( ! class_exists( '\WC_Install' ) ) {
		echo 'Warning: no WooCommerce installer found; tests touching WooCommerce tables will fail.' . PHP_EOL;
		$installed = false;

		return $installed;
	}

	echo 'Installing WooCommerce...' . PHP_EOL;

	// The post store, not HPOS. The suite's tables are created by the WordPress test
	// installer, and WooCommerce's HPOS queries join the orders table to itself through a
	// temporary table — which MySQL refuses with "Can't reopen table: 'orders'". wpdb then
	// *prints* that error, and any request that was building a JSON response ends up with HTML
	// in it. The driver writes through the CRUD layer, which works identically on either store,
	// so nothing is lost by testing on the one the test database can serve.
	update_option( 'woocommerce_feature_custom_order_tables_enabled', 'no' );
	update_option( 'woocommerce_custom_orders_table_enabled', 'no' );

	\WC_Install::install();

	// WooCommerce adds the customer and shop_manager roles during install, so the globals have
	// to be rebuilt for a test that creates a customer to find the role.
	$GLOBALS['wp_roles'] = null;
	wp_roles();

	$installed = true;

	return $installed;
}

/**
 * Install StoreSeeder for testing
 */
function install_storeseeder() {
	echo 'Installing StoreSeeder...' . PHP_EOL;

	// Clean up existing tables.
	storeseeder_truncate_table_data();

	// Activate the plugin. The method is activate_plugin(); the old activate()
	// call fataled here before any test could run.
	if ( function_exists( 'storeseeder' ) && method_exists( storeseeder(), 'activate_plugin' ) ) {
		storeseeder()->activate_plugin();
	}
}

// Install dependencies and our plugin.
tests_add_filter( 'setup_theme', 'install_fluent_cart' );
tests_add_filter( 'setup_theme', 'storeseeder_install_woocommerce_tables' );
tests_add_filter( 'setup_theme', 'install_storeseeder' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';