<?php
/**
 * PHPUnit bootstrap file for StoreSeeder
 */

// Define plugin directories..
define( 'TEST_STORESEEDER_DIR', dirname( __DIR__, 2 ) );
define( 'TEST_FC_DIR', dirname( __DIR__, 3 ) . '/fluent-cart' );

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
		'storeseeder_generated_data',
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
	// Load Fluent Cart if the directory exists.
	require TEST_FC_DIR . '/fluent-cart.php';

	// Load our plugin.
	require TEST_STORESEEDER_DIR . '/storeseeder.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Install Fluent Cart for testing
 */
function install_fluent_cart() {
	// Skip if Fluent Cart doesn't exist..
	if ( ! file_exists( TEST_FC_DIR . '/fluent-cart.php' ) ) {
		echo 'Warning: Fluent Cart plugin not found. Some tests may fail.' . PHP_EOL;
		return;
	}

	echo 'Installing Fluent Cart...' . PHP_EOL;

	// Install Fluent Cart if it has an installation method.
	\FluentCart\fluent_cart_install();

	// Reload capabilities after install.
	if ( version_compare( $GLOBALS['wp_version'], '4.7', '<' ) ) {
		$GLOBALS['wp_roles']->reinit();
	} else {
		$GLOBALS['wp_roles'] = null;
		wp_roles();
	}
}

/**
 * Install StoreSeeder for testing
 */
function install_storeseeder() {
	echo 'Installing StoreSeeder...' . PHP_EOL;

	// Clean up existing tables.
	storeseeder_truncate_table_data();

	// Activate the plugin.
	if ( function_exists( 'storeseeder' ) ) {
		storeseeder()->activate();
	}
}

// Install dependencies and our plugin.
tests_add_filter( 'setup_theme', 'install_fluent_cart' );
tests_add_filter( 'setup_theme', 'install_storeseeder' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';